<?php

namespace Tests\Feature\Wallet;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WatchPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_invoice_paid_when_unconfirmed_payment_detected(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'abc123',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 1_000_000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('wallet:watch-payments')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('abc123', $invoice->txid);
        $this->assertSame(1_000_000, $invoice->payment_amount_sat);
        $this->assertSame(0, $invoice->payment_confirmations);
        $this->assertNotNull($invoice->payment_detected_at);
        $this->assertNull($invoice->payment_confirmed_at);
    }

    public function test_command_updates_confirmations_when_block_is_known(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'def456',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 250000,
                        'block_time' => Carbon::now()->subMinutes(5)->timestamp,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 1_000_000,
                        ],
                    ],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('250002', 200),
        ]);

        $this->artisan('wallet:watch-payments')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('def456', $invoice->txid);
        $this->assertSame(1_000_000, $invoice->payment_amount_sat);
        $this->assertSame(3, $invoice->payment_confirmations);
        $this->assertNotNull($invoice->payment_confirmed_at);
        $this->assertNotNull($invoice->paid_at);
    }

    private function makeInvoice(): Invoice
    {
        $user = User::factory()->create();
        $user->walletSetting()->create([
            'network' => 'testnet',
            'bip84_xpub' => 'tpubD6Nz...',
            'next_derivation_index' => 0,
        ]);

        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => 'billing@example.com',
        ]);

        return Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-1001',
            'description' => 'Consulting',
            'amount_usd' => 400,
            'btc_rate' => 40000,
            'amount_btc' => 0.01,
            'payment_address' => 'tb1qq0exampleaddress0000000000000',
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
    }
}
