<?php

namespace Tests\Feature\Wallet;

use App\Models\Client;
use App\Models\InvoicePayment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BtcRate;
use App\Services\Blockchain\MempoolClient;
use App\Services\WalletKeyLineage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WatchPaymentsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_command_uses_testnet4_base_when_wallet_network_is_testnet4(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoiceWithNetwork('testnet4');

        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet/api');
        config()->set('blockchain.mempool.testnet4_base', 'https://mempool.example/testnet4/api');
        app()->forgetInstance(MempoolClient::class);

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

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'payment_acknowledgment_client',
            'context_key' => 'abc123',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'payment_acknowledgment_issuer',
            'context_key' => 'abc123',
        ]);
    }

    public function test_command_uses_testnet3_base_when_wallet_network_is_testnet3(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoiceWithNetwork('testnet3');

        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet4/api');
        config()->set('blockchain.mempool.testnet3_base', 'https://mempool.example/testnet3/api');
        app()->forgetInstance(MempoolClient::class);

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

    public function test_command_uses_invoice_lineage_even_when_current_wallet_network_differs(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        $invoice = $this->makeInvoiceWithNetwork('testnet4');
        $invoice->user->walletSetting()->update([
            'network' => 'mainnet',
        ]);

        config()->set('blockchain.mempool.mainnet_base', 'https://mempool.example/mainnet/api');
        config()->set('blockchain.mempool.testnet4_base', 'https://mempool.example/testnet4/api');
        app()->forgetInstance(MempoolClient::class);
        $base = config('blockchain.mempool.testnet4_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'lineage123',
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

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoice->refresh();
        $this->assertSame('lineage123', $invoice->txid);
        $this->assertSame('pending', $invoice->status);
    }

    public function test_command_skips_invoice_that_lacks_wallet_lineage(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->forceFill([
            'wallet_key_fingerprint' => null,
            'wallet_network' => null,
        ])->save();

        Http::fake();

        $this->artisan('wallet:watch-payments')
            ->expectsOutput("Invoice {$invoice->id} lacks wallet lineage and was skipped.")
            ->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoice_payments', 0);
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

    public function test_ignored_unconfirmed_payment_is_not_dropped_when_missing_from_mempool(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-03 19:00:00', 'UTC'));
        $invoice = $this->makeInvoice();
        $base = config('blockchain.mempool.testnet_base');

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'ignored-stale-tx',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now()->subHours(1),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
            'ignored_at' => Carbon::now()->subMinutes(30),
            'ignore_reason' => 'Wrong invoice',
        ]);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([], 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'ignored-stale-tx',
        ]);
    }

    public function test_watcher_keeps_ignored_payment_ignored_when_txid_reappears(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-03 20:00:00', 'UTC'));
        $invoice = $this->makeInvoice();
        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'ignored-live-tx',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now()->subHours(1),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
            'ignored_at' => Carbon::now()->subMinutes(30),
            'ignore_reason' => 'Wrong invoice',
        ]);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::response([
                [
                    'txid' => 'ignored-live-tx',
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

        $payment->refresh();
        $invoice->refresh();

        $this->assertNotNull($payment->ignored_at);
        $this->assertSame('Wrong invoice', $payment->ignore_reason);
        $this->assertSame('sent', $invoice->status);
        $this->assertNull($invoice->txid);
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

    public function test_repeated_partial_payment_detection_no_longer_creates_a_separate_partial_warning_family(): void
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
        $this->assertNull($invoice->last_partial_warning_sent_at);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_partial_warning',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_partial_warning',
        ]);
    }

    public function test_repeated_partial_payment_detection_does_not_enqueue_legacy_partial_warnings_on_repeat_runs(): void
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

        $this->assertEquals(0, \App\Models\InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'client_partial_warning')->count());
        $this->assertEquals(0, \App\Models\InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'issuer_partial_warning')->count());
    }

    public function test_watcher_keeps_payment_acknowledgment_deduped_per_txid_but_allows_a_second_txid(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-06 09:00:00', 'UTC'));

        $invoice = $this->makeInvoice();

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 46_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$invoice->payment_address}/txs" => Http::sequence()
                ->push([
                    [
                        'txid' => 'ack-tx-1',
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
                ], 200)
                ->push([
                    [
                        'txid' => 'ack-tx-1',
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
                        'txid' => 'ack-tx-2',
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
        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $this->assertSame(1, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'payment_acknowledgment_client')
            ->where('context_key', 'ack-tx-1')
            ->count());

        $this->assertSame(1, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'payment_acknowledgment_issuer')
            ->where('context_key', 'ack-tx-1')
            ->count());

        $this->assertSame(1, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'payment_acknowledgment_client')
            ->where('context_key', 'ack-tx-2')
            ->count());

        $this->assertSame(1, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'payment_acknowledgment_issuer')
            ->where('context_key', 'ack-tx-2')
            ->count());
    }

    public function test_watcher_queues_payment_acknowledgment_and_owner_paid_notice_when_payment_marks_invoice_paid(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2025-01-06 10:00:00', 'UTC'));

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
                    'txid' => 'ack-before-receipt',
                    'status' => [
                        'confirmed' => true,
                        'block_height' => 250100,
                        'block_time' => Carbon::now()->subMinute()->timestamp,
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

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $clientAck = \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'payment_acknowledgment_client')
            ->where('context_key', 'ack-before-receipt')
            ->first();

        $ownerPaidNotice = \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'issuer_paid_notice')
            ->first();

        $this->assertNotNull($clientAck);
        $this->assertNotNull($ownerPaidNotice);
        $this->assertSame(0, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'receipt')
            ->count());
        $this->assertSame(1, \App\Models\InvoiceDelivery::query()
            ->where('invoice_id', $invoice->id)
            ->where('type', 'issuer_paid_notice')
            ->count());
    }

    public function test_command_flags_implicated_invoices_and_matching_wallets_when_payment_collision_evidence_is_detected(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-06 12:00:00', 'UTC'));
        $address = 'tb1qcollisionshared0000000000000000000';
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $ownerA->walletSetting()->create([
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-collision-owner-a',
        ]);
        $ownerB->walletSetting()->create([
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-collision-owner-b',
        ]);

        $invoiceA = $this->createInvoiceForUser(
            $ownerA,
            'INV-2001',
            $address,
            'vpub-collision-owner-a'
        );
        $invoiceB = $this->createInvoiceForUser(
            $ownerB,
            'INV-2002',
            $address,
            'vpub-collision-owner-b'
        );
        $unrelatedInvoice = $this->createInvoiceForUser(
            $ownerA,
            'INV-2003',
            'tb1qunrelatedaddress0000000000000000000',
            'vpub-collision-owner-a'
        );

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 42_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$address}/txs" => Http::response([
                [
                    'txid' => 'collision-payment-1',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $address,
                            'value' => 1_000_000,
                        ],
                    ],
                ],
            ], 200),
            "{$base}/address/{$unrelatedInvoice->payment_address}/txs" => Http::response([], 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoiceA->refresh();
        $invoiceB->refresh();
        $unrelatedInvoice->refresh();
        $ownerA->walletSetting->refresh();
        $ownerB->walletSetting->refresh();

        $this->assertTrue($invoiceA->unsupported_configuration_flagged);
        $this->assertSame('evidence', $invoiceA->unsupported_configuration_source);
        $this->assertSame('payment_collision', $invoiceA->unsupported_configuration_reason);
        $this->assertStringContainsString('collision-payment-1', (string) $invoiceA->unsupported_configuration_details);
        $this->assertStringContainsString('INV-2002', (string) $invoiceA->unsupported_configuration_details);

        $this->assertTrue($invoiceB->unsupported_configuration_flagged);
        $this->assertSame('evidence', $invoiceB->unsupported_configuration_source);
        $this->assertSame('payment_collision', $invoiceB->unsupported_configuration_reason);
        $this->assertStringContainsString('INV-2001', (string) $invoiceB->unsupported_configuration_details);

        $this->assertFalse($unrelatedInvoice->unsupported_configuration_flagged);

        $this->assertTrue((bool) $ownerA->walletSetting->unsupported_configuration_active);
        $this->assertSame('evidence', $ownerA->walletSetting->unsupported_configuration_source);
        $this->assertSame('payment_collision', $ownerA->walletSetting->unsupported_configuration_reason);

        $this->assertTrue((bool) $ownerB->walletSetting->unsupported_configuration_active);
        $this->assertSame('evidence', $ownerB->walletSetting->unsupported_configuration_source);
        $this->assertSame('payment_collision', $ownerB->walletSetting->unsupported_configuration_reason);
    }

    public function test_command_does_not_flag_current_wallet_when_collision_evidence_belongs_to_old_invoice_lineage(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-06 13:00:00', 'UTC'));
        $address = 'tb1qoldlineagecollision000000000000000';
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $ownerA->walletSetting()->create([
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-owner-a-current',
        ]);
        $ownerB->walletSetting()->create([
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-owner-b-current',
        ]);

        $invoiceA = $this->createInvoiceForUser(
            $ownerA,
            'INV-3001',
            $address,
            'vpub-owner-a-old'
        );
        $invoiceB = $this->createInvoiceForUser(
            $ownerB,
            'INV-3002',
            $address,
            'vpub-owner-b-current'
        );

        $base = config('blockchain.mempool.testnet_base');

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 42_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        Http::fake([
            "{$base}/address/{$address}/txs" => Http::response([
                [
                    'txid' => 'collision-payment-old-lineage',
                    'status' => [
                        'confirmed' => false,
                        'block_height' => null,
                        'block_time' => null,
                    ],
                    'vout' => [
                        [
                            'scriptpubkey_address' => $address,
                            'value' => 1_000_000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('wallet:watch-payments')->assertExitCode(0);

        $invoiceA->refresh();
        $invoiceB->refresh();
        $ownerA->walletSetting->refresh();
        $ownerB->walletSetting->refresh();

        $this->assertTrue($invoiceA->unsupported_configuration_flagged);
        $this->assertTrue($invoiceB->unsupported_configuration_flagged);

        $this->assertFalse((bool) $ownerA->walletSetting->unsupported_configuration_active);
        $this->assertNull($ownerA->walletSetting->unsupported_configuration_source);
        $this->assertNull($ownerA->walletSetting->unsupported_configuration_reason);

        $this->assertTrue((bool) $ownerB->walletSetting->unsupported_configuration_active);
        $this->assertSame('evidence', $ownerB->walletSetting->unsupported_configuration_source);
        $this->assertSame('payment_collision', $ownerB->walletSetting->unsupported_configuration_reason);
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
            'wallet_key_fingerprint' => app(WalletKeyLineage::class)->fingerprint($network, 'tpubD6Nz...'),
            'wallet_network' => $network,
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
    }

    private function createInvoiceForUser(User $user, string $number, string $address, string $xpub, string $network = 'testnet'): Invoice
    {
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => "billing-{$number}@example.com",
        ]);

        return Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => $number,
            'description' => 'Consulting',
            'amount_usd' => 400,
            'btc_rate' => 40000,
            'amount_btc' => 0.01,
            'payment_address' => $address,
            'wallet_key_fingerprint' => $this->walletFingerprint($network, $xpub),
            'wallet_network' => $network,
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
    }

    private function walletFingerprint(string $network, string $xpub): string
    {
        return app(WalletKeyLineage::class)->fingerprint($network, $xpub);
    }
}
