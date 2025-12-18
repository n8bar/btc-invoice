<?php

namespace Tests\Feature\Wallet;

use App\Models\Client;
use App\Models\InvoicePayment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BtcRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WatchPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_uses_testnet4_base_when_wallet_network_is_testnet4(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoiceWithNetwork('testnet4');

        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet/api');
        config()->set('blockchain.mempool.testnet4_base', 'https://mempool.example/testnet4/api');

        $base = config('blockchain.mempool.testnet4_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

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
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('abc123', $invoice->txid);
        $this->assertSame(0, $invoice->payment_amount_sat);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'abc123',
            'sats_received' => 1_000_000,
        ]);
    }

    public function test_command_uses_testnet3_base_when_wallet_network_is_testnet3(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoiceWithNetwork('testnet3');

        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet4/api');
        config()->set('blockchain.mempool.testnet3_base', 'https://mempool.example/testnet3/api');

        $base = config('blockchain.mempool.testnet3_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

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
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('abc123', $invoice->txid);
        $this->assertSame(0, $invoice->payment_amount_sat);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'abc123',
            'sats_received' => 1_000_000,
        ]);
    }

    public function test_command_marks_invoice_paid_when_unconfirmed_payment_detected(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

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
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('abc123', $invoice->txid);
        $this->assertSame(0, $invoice->payment_amount_sat);
        $this->assertNotNull($invoice->payment_detected_at);
        $this->assertNull($invoice->payment_confirmed_at);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'abc123',
            'sats_received' => 1_000_000,
        ]);
    }

    public function test_command_updates_confirmations_when_block_is_known(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-02 09:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 38_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

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
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('def456', $invoice->txid);
        $this->assertSame(1_000_000, $invoice->payment_amount_sat);
        $this->assertNotNull($invoice->payment_confirmed_at);
        $this->assertNull($invoice->paid_at);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'def456',
            'block_height' => 250000,
        ]);
    }

    public function test_command_marks_invoice_partial_when_underpaid(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-03 10:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 35_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'under123',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 250010,
                        'block_time' => Carbon::now()->subMinutes(4)->timestamp,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 400_000,
                        ],
                    ],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('250011', 200),
        ]);

        $this->artisan('wallet:watch-payments')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(400_000, $invoice->payment_amount_sat);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'under123',
            'sats_received' => 400_000,
        ]);
    }

    public function test_unconfirmed_payment_is_dropped_when_missing_from_mempool(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-03 18:00:00', 'UTC'));
        $invoice = $this->makeInvoice();
        $base = config('blockchain.mempool.testnet_base');

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'stale-tx',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now()->subHours(1),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
        ]);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([], 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $this->assertDatabaseMissing('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'stale-tx',
        ]);

        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertNull($invoice->txid);
        $this->assertSame(0, $invoice->payment_amount_sat);
    }

    public function test_command_records_multiple_partial_transactions(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-04 12:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 50_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'partial-aaa',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 400_000,
                        ],
                    ],
                ],
                [
                    'txid' => 'partial-bbb',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 250100,
                        'block_time' => Carbon::now()->subMinutes(5)->timestamp,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 600_000,
                        ],
                    ],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('250105', 200),
        ]);

        $this->artisan('wallet:watch-payments')
            ->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(600_000, $invoice->payment_amount_sat);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'partial-aaa',
            'sats_received' => 400_000,
        ]);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'partial-bbb',
            'sats_received' => 600_000,
        ]);
    }

    public function test_partial_warning_emails_sent_on_second_payment_attempt(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-05 08:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 48_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'warn-aaa',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 300_000,
                        ],
                    ],
                ],
                [
                    'txid' => 'warn-bbb',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 200_000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('pending', $invoice->status);
        $this->assertNotNull($invoice->last_partial_warning_sent_at);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_partial_warning',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'owner_partial_warning',
        ]);
    }

    public function test_partial_warning_not_resent_within_cooldown(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-06 08:00:00', 'UTC'));
        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 46_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        $payload = [
            [
                'txid' => 'warn-ccc',
                'status' => [
                    'confirmed' => false,
                    'block_height' => null,
                    'block_time' => null,
                ],
                'vout' => [
                    [
                        'scriptpubkey_address' => $invoice->payment_address,
                        'value' => 350_000,
                    ],
                ],
            ],
            [
                'txid' => 'warn-ddd',
                'status' => [
                    'confirmed' => false,
                    'block_height' => null,
                    'block_time' => null,
                ],
                'vout' => [
                    [
                        'scriptpubkey_address' => $invoice->payment_address,
                        'value' => 350_000,
                    ],
                ],
            ],
        ];

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response($payload, 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);
        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $this->assertEquals(1, \App\Models\InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'client_partial_warning')->count());
        $this->assertEquals(1, \App\Models\InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'owner_partial_warning')->count());
    }

    private function makeInvoice(): Invoice
    {
        return $this->makeInvoiceWithNetwork('testnet');
    }

    private function makeInvoiceWithNetwork(string $network): Invoice
    {
        $user = User::factory()->create();
        $user->walletSetting()->create([
            'network' => $network,
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
