<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\BtcRate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\CreatesTestInvoices;

/**
 * Gap 3 — Invoice state machine
 *
 * Covers transitions and blocked transitions not already exercised by
 * WatchPaymentsCommandTest or InvoicePaymentCorrectionTest.
 */
class InvoiceStatusTransitionTest extends TestCase
{
    use DatabaseTransactions, CreatesTestInvoices;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget(BtcRate::CACHE_KEY);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // draft → sent via the manual set-status action
    // -----------------------------------------------------------------------

    public function test_draft_transitions_to_sent_via_set_status_action(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['status' => 'draft']);

        $this->actingAs($owner)
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.set-status', ['invoice' => $invoice, 'action' => 'sent']))
            ->assertRedirect()
            ->assertSessionHas('status', 'Status updated.');

        $this->assertSame('sent', $invoice->fresh()->status);
    }

    // -----------------------------------------------------------------------
    // sent → partial on first underpayment (via the watcher)
    // -----------------------------------------------------------------------

    public function test_sent_transitions_to_partial_on_first_underpayment(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 10:00:00', 'UTC'));

        $invoice = $this->makeInvoiceWithNetwork('testnet');
        // makeInvoiceWithNetwork creates a 400 USD / 40k rate / 0.01 BTC invoice = 1_000_000 sats

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'partial-first-tx',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 300_000,
                        'block_time' => Carbon::now()->subMinutes(5)->timestamp,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $invoice->payment_address,
                            'value' => 500_000, // half of 1_000_000 required
                        ],
                    ],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('300001', 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertSame(500_000, $invoice->payment_amount_sat);
        $this->assertNull($invoice->paid_at);
    }

    // -----------------------------------------------------------------------
    // partial → paid when second payment resolves the balance (via the watcher)
    // -----------------------------------------------------------------------

    public function test_partial_transitions_to_paid_when_balance_resolved(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-02 10:00:00', 'UTC'));

        $invoice = $this->makeInvoiceWithNetwork('testnet');
        // 1_000_000 sats required

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::sequence()
                // First run — partial
                ->push([
                    [
                        'txid' => 'first-partial',
                        'status' => ['confirmed' => true, 'block_height' => 300_100, 'block_time' => Carbon::now()->subMinutes(10)->timestamp],
                        'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 600_000]],
                    ],
                ], 200)
                // Second run — balance resolved
                ->push([
                    [
                        'txid' => 'first-partial',
                        'status' => ['confirmed' => true, 'block_height' => 300_100, 'block_time' => Carbon::now()->subMinutes(10)->timestamp],
                        'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 600_000]],
                    ],
                    [
                        'txid' => 'second-completes',
                        'status' => ['confirmed' => true, 'block_height' => 300_200, 'block_time' => Carbon::now()->subMinutes(2)->timestamp],
                        'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 500_000]],
                    ],
                ], 200),
            "{$base}/blocks/tip/height" => Http::response('300201', 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);
        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1_100_000, $invoice->payment_amount_sat);
        $this->assertNotNull($invoice->paid_at);
    }

    // -----------------------------------------------------------------------
    // sent → paid on full payment in a single transaction (via the watcher)
    // -----------------------------------------------------------------------

    public function test_sent_transitions_to_paid_on_full_payment_in_one_transaction(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-03 10:00:00', 'UTC'));

        $invoice = $this->makeInvoiceWithNetwork('testnet');
        // 1_000_000 sats required

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'full-pay-one-shot',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 300_300,
                        'block_time' => Carbon::now()->subMinutes(3)->timestamp,
                    ],
                    'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 1_000_000]],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('300301', 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1_000_000, $invoice->payment_amount_sat);
        $this->assertNotNull($invoice->paid_at);
    }

    // -----------------------------------------------------------------------
    // Watcher skips voided invoices (transition blocked)
    // -----------------------------------------------------------------------

    public function test_watcher_does_not_process_voided_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-04 10:00:00', 'UTC'));

        $invoice = $this->makeInvoiceWithNetwork('testnet');
        $invoice->forceFill(['status' => 'void'])->save();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'payment-on-void',
                    'status' => ['confirmed' => true, 'block_height' => 300_400, 'block_time' => Carbon::now()->subMinute()->timestamp],
                    'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 1_000_000]],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('300401', 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        // Invoice stays void; no payment recorded
        $invoice->refresh();
        $this->assertSame('void', $invoice->status);
        $this->assertDatabaseMissing('invoice_payments', ['invoice_id' => $invoice->id]);
    }

    // -----------------------------------------------------------------------
    // Watcher skips trashed (soft-deleted) invoices (transition blocked)
    // -----------------------------------------------------------------------

    public function test_watcher_does_not_process_trashed_invoice(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-05 10:00:00', 'UTC'));

        $invoice = $this->makeInvoiceWithNetwork('testnet');
        $invoice->delete(); // soft-delete

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'payment-on-trash',
                    'status' => ['confirmed' => true, 'block_height' => 300_500, 'block_time' => Carbon::now()->subMinute()->timestamp],
                    'vout' => [['scriptpubkey_address' => $invoice->payment_address, 'value' => 1_000_000]],
                ],
            ], 200),
            "{$base}/blocks/tip/height" => Http::response('300501', 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        // Trashed invoice is not touched; no payment recorded
        $this->assertDatabaseMissing('invoice_payments', ['invoice_id' => $invoice->id]);
        $this->assertSame('sent', Invoice::withTrashed()->find($invoice->id)->status);
    }
}
