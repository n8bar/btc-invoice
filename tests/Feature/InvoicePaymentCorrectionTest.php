<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\BtcRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class InvoicePaymentCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget(BtcRate::CACHE_KEY);
        parent::tearDown();
    }

    public function test_owner_can_ignore_payment_reopen_invoice_and_hide_ignored_row_from_print_surfaces(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-10 12:00:00', 'UTC'));
        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 50_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);
        Http::fake([
            'https://api.coinbase.com/*' => Http::response([
                'data' => ['amount' => '50000.00'],
            ], 200),
        ]);
        Log::spy();

        [$owner, $client, $invoice] = $this->makeInvoice([
            'amount_usd' => 5,
            'btc_rate' => 50_000,
            'amount_btc' => 0.0001,
            'public_enabled' => true,
            'public_token' => 'tok-correction-ignore',
            'public_expires_at' => Carbon::now()->addDay(),
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-ignore-1',
            'sats_received' => 10_000,
            'detected_at' => Carbon::now()->subMinutes(5),
            'confirmed_at' => Carbon::now()->subMinutes(4),
            'usd_rate' => 50_000,
            'fiat_amount' => 5.00,
        ]);

        $invoice->refresh()->refreshPaymentLedger();
        $invoice->refresh();

        $receipt = $invoice->deliveries()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'receipt',
            'status' => 'queued',
            'recipient' => $client->email,
            'dispatched_at' => now(),
        ]);

        $ownerNotice = $invoice->deliveries()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'owner_paid_notice',
            'status' => 'queued',
            'recipient' => $owner->email,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.ignore', [$invoice, $payment]), [
                'correction_payment_id' => $payment->id,
                'ignore_reason' => 'Shared wallet receive outside invoice flow',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $payment->refresh();
        $receipt->refresh();
        $ownerNotice->refresh();

        $this->assertSame('sent', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(0, $invoice->payment_amount_sat);
        $this->assertNull($invoice->txid);
        $this->assertSame(0, $invoice->payment_confirmations);
        $this->assertNotNull($payment->ignored_at);
        $this->assertSame($owner->id, $payment->ignored_by_user_id);
        $this->assertSame('Shared wallet receive outside invoice flow', $payment->ignore_reason);
        $this->assertSame('skipped', $receipt->status);
        $this->assertSame('skipped', $ownerNotice->status);

        $this->actingAs($owner)
            ->get(route('invoices.show', $invoice->fresh('payments')))
            ->assertOk()
            ->assertSee('Ignored for invoice math', false)
            ->assertSee('Shared wallet receive outside invoice flow', false);

        $this->actingAs($owner)
            ->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertDontSee('tx-ignore-1', false)
            ->assertDontSee('Shared wallet receive outside invoice flow', false);

        $this->get(route('invoices.public-print', ['token' => 'tok-correction-ignore']))
            ->assertOk()
            ->assertDontSee('tx-ignore-1', false)
            ->assertDontSee('Shared wallet receive outside invoice flow', false);

        Log::shouldHaveReceived('info')
            ->with('invoice.payment.ignored', \Mockery::on(function (array $context) use ($invoice, $payment, $owner): bool {
                return $context['invoice_id'] === $invoice->id
                    && $context['payment_id'] === $payment->id
                    && $context['user_id'] === $owner->id
                    && $context['txid'] === 'tx-ignore-1'
                    && $context['status_before'] === 'paid'
                    && $context['status_after'] === 'sent'
                    && $context['ignore_reason'] === 'Shared wallet receive outside invoice flow';
            }))
            ->once();
    }

    public function test_owner_can_restore_ignored_payment_and_skip_stale_underpay_and_partial_deliveries(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-11 09:00:00', 'UTC'));
        Log::spy();

        [$owner, $client, $invoice] = $this->makeInvoice([
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-partial-a',
            'sats_received' => 60_000,
            'detected_at' => Carbon::now()->subMinutes(10),
            'confirmed_at' => Carbon::now()->subMinutes(9),
            'usd_rate' => 50_000,
            'fiat_amount' => 30.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-partial-b',
            'sats_received' => 60_000,
            'detected_at' => Carbon::now()->subMinutes(8),
            'confirmed_at' => Carbon::now()->subMinutes(7),
            'usd_rate' => 50_000,
            'fiat_amount' => 30.00,
        ]);

        $ignoredPayment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-restore-1',
            'sats_received' => 80_000,
            'detected_at' => Carbon::now()->subMinutes(6),
            'confirmed_at' => Carbon::now()->subMinutes(5),
            'usd_rate' => 50_000,
            'fiat_amount' => 40.00,
            'ignored_at' => Carbon::now()->subMinute(),
            'ignored_by_user_id' => $owner->id,
            'ignore_reason' => 'Wrong invoice',
        ]);

        $invoice->refresh()->refreshPaymentLedger();
        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);

        $deliveries = collect([
            $invoice->deliveries()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $owner->id,
                'type' => 'client_underpay_alert',
                'status' => 'queued',
                'recipient' => $client->email,
                'dispatched_at' => now(),
            ]),
            $invoice->deliveries()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $owner->id,
                'type' => 'owner_underpay_alert',
                'status' => 'queued',
                'recipient' => $owner->email,
                'dispatched_at' => now(),
            ]),
            $invoice->deliveries()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $owner->id,
                'type' => 'client_partial_warning',
                'status' => 'queued',
                'recipient' => $client->email,
                'dispatched_at' => now(),
            ]),
            $invoice->deliveries()->create([
                'invoice_id' => $invoice->id,
                'user_id' => $owner->id,
                'type' => 'owner_partial_warning',
                'status' => 'queued',
                'recipient' => $owner->email,
                'dispatched_at' => now(),
            ]),
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.restore', [$invoice, $ignoredPayment]))
            ->assertRedirect();

        $invoice->refresh();
        $ignoredPayment->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(200_000, $invoice->payment_amount_sat);
        $this->assertNull($ignoredPayment->ignored_at);
        $this->assertNull($ignoredPayment->ignored_by_user_id);
        $this->assertNull($ignoredPayment->ignore_reason);

        $deliveries->each(fn ($delivery) => $delivery->refresh());
        $this->assertTrue($deliveries->every(fn ($delivery) => $delivery->status === 'skipped'));

        Log::shouldHaveReceived('info')
            ->with('invoice.payment.restored', \Mockery::on(function (array $context) use ($invoice, $ignoredPayment, $owner): bool {
                return $context['invoice_id'] === $invoice->id
                    && $context['payment_id'] === $ignoredPayment->id
                    && $context['user_id'] === $owner->id
                    && $context['txid'] === 'tx-restore-1'
                    && $context['status_before'] === 'partial'
                    && $context['status_after'] === 'paid';
            }))
            ->once();
    }

    public function test_non_owner_cannot_ignore_payment(): void
    {
        [$owner, , $invoice] = $this->makeInvoice();
        $other = User::factory()->create();

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-forbidden-ignore',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $this->actingAs($other)
            ->patch(route('invoices.payments.ignore', [$invoice, $payment]), [
                'ignore_reason' => 'Not my invoice',
            ])
            ->assertForbidden();

        $payment->refresh();
        $this->assertNull($payment->ignored_at);
    }

    public function test_payment_correction_returns_404_for_mismatched_invoice_payment_pair(): void
    {
        [$owner, , $invoiceA] = $this->makeInvoice(['number' => 'INV-CORRECT-A']);
        [, , $invoiceB] = $this->makeInvoice(['number' => 'INV-CORRECT-B'], $owner);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoiceB->id,
            'txid' => 'tx-wrong-parent',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.ignore', [$invoiceA, $payment]), [
                'ignore_reason' => 'Wrong pair',
            ])
            ->assertNotFound();
    }

    public function test_manual_adjustments_cannot_be_ignored(): void
    {
        [$owner, , $invoice] = $this->makeInvoice();

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'manual-ignore-attempt',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
            'is_adjustment' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.ignore', [$invoice, $payment]), [
                'ignore_reason' => 'Should fail',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Manual adjustments cannot be ignored.');

        $payment->refresh();
        $this->assertNull($payment->ignored_at);
    }

    private function makeInvoice(array $invoiceOverrides = [], ?User $owner = null): array
    {
        $owner ??= User::factory()->create(['email' => 'owner@example.com']);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'client@example.com',
            'notes' => null,
        ]);

        $defaults = [
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-CORRECT-1001',
            'description' => 'Consulting services',
            'amount_usd' => 10,
            'btc_rate' => 50_000,
            'amount_btc' => 0.0002,
            'payment_address' => 'tb1qcorrectionexampleaddress0000000000',
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create($defaults);
        if ($invoiceOverrides !== []) {
            $invoice->forceFill($invoiceOverrides)->save();
        }

        return [$owner, $client, $invoice];
    }
}
