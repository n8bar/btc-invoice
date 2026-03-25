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

    public function test_owner_can_reattribute_payment_to_another_owned_invoice_and_public_surfaces_follow_active_destination(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-12 10:00:00', 'UTC'));
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

        [$owner, $client, $sourceInvoice] = $this->makeInvoice([
            'number' => 'INV-REATTR-SRC',
            'amount_usd' => 50,
            'btc_rate' => 50_000,
            'amount_btc' => 0.001,
            'public_enabled' => true,
            'public_token' => 'tok-reattr-src',
            'public_expires_at' => Carbon::now()->addDay(),
        ]);
        [, , $destinationInvoice] = $this->makeInvoice([
            'number' => 'INV-REATTR-DEST',
            'amount_usd' => 50,
            'btc_rate' => 50_000,
            'amount_btc' => 0.001,
            'public_enabled' => true,
            'public_token' => 'tok-reattr-dest',
            'public_expires_at' => Carbon::now()->addDay(),
        ], $owner);

        $payment = InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'txid' => 'tx-reattribute-1',
            'sats_received' => 100_000,
            'detected_at' => Carbon::now()->subMinutes(5),
            'confirmed_at' => Carbon::now()->subMinutes(4),
            'usd_rate' => 50_000,
            'fiat_amount' => 50.00,
        ]);

        $sourceInvoice->refresh()->refreshPaymentLedger();
        $destinationInvoice->refresh()->refreshPaymentLedger();
        $sourceInvoice->refresh();
        $destinationInvoice->refresh();

        $receipt = $sourceInvoice->deliveries()->create([
            'invoice_id' => $sourceInvoice->id,
            'user_id' => $owner->id,
            'type' => 'receipt',
            'status' => 'queued',
            'recipient' => $client->email,
            'dispatched_at' => now(),
        ]);

        $ownerNotice = $sourceInvoice->deliveries()->create([
            'invoice_id' => $sourceInvoice->id,
            'user_id' => $owner->id,
            'type' => 'owner_paid_notice',
            'status' => 'queued',
            'recipient' => $owner->email,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.reattribute', [$sourceInvoice, $payment]), [
                'correction_payment_id' => $payment->id,
                'destination_invoice_id' => $destinationInvoice->id,
                'reattribute_reason' => 'Later payment belonged to the newer invoice.',
            ])
            ->assertRedirect();

        $payment->refresh();
        $sourceInvoice->refresh();
        $destinationInvoice->refresh();
        $receipt->refresh();
        $ownerNotice->refresh();

        $this->assertSame($destinationInvoice->id, $payment->accounting_invoice_id);
        $this->assertNotNull($payment->reattributed_at);
        $this->assertSame($owner->id, $payment->reattributed_by_user_id);
        $this->assertSame('Later payment belonged to the newer invoice.', $payment->reattribute_reason);
        $this->assertSame('sent', $sourceInvoice->status);
        $this->assertNull($sourceInvoice->paid_at);
        $this->assertSame(0, $sourceInvoice->payment_amount_sat);
        $this->assertNull($sourceInvoice->txid);
        $this->assertSame('paid', $destinationInvoice->status);
        $this->assertSame(100_000, $destinationInvoice->payment_amount_sat);
        $this->assertSame('tx-reattribute-1', $destinationInvoice->txid);
        $this->assertSame('skipped', $receipt->status);
        $this->assertSame('skipped', $ownerNotice->status);

        $this->actingAs($owner)
            ->get(route('invoices.show', $sourceInvoice))
            ->assertOk()
            ->assertSee('Correction menu: Reapplied Elsewhere', false)
            ->assertSee($destinationInvoice->number, false)
            ->assertSee('No longer counts on this invoice.', false);

        $this->actingAs($owner)
            ->get(route('invoices.show', $destinationInvoice))
            ->assertOk()
            ->assertSee('Correction menu: Applied Here', false)
            ->assertSee($sourceInvoice->number, false)
            ->assertSee('Edit notes on', false);

        $this->actingAs($owner)
            ->get(route('invoices.print', $sourceInvoice))
            ->assertOk()
            ->assertDontSee('tx-reattribute-1', false);

        $this->actingAs($owner)
            ->get(route('invoices.print', $destinationInvoice))
            ->assertOk()
            ->assertSee('tx-reattribute-1', false);

        $this->get(route('invoices.public-print', ['token' => 'tok-reattr-src']))
            ->assertOk()
            ->assertDontSee('tx-reattribute-1', false);

        $this->get(route('invoices.public-print', ['token' => 'tok-reattr-dest']))
            ->assertOk()
            ->assertSee('tx-reattribute-1', false);

        Log::shouldHaveReceived('info')
            ->with('invoice.payment.reattributed', \Mockery::on(function (array $context) use ($owner, $payment, $sourceInvoice, $destinationInvoice): bool {
                return $context['invoice_id'] === $sourceInvoice->id
                    && $context['payment_id'] === $payment->id
                    && $context['user_id'] === $owner->id
                    && $context['txid'] === 'tx-reattribute-1'
                    && $context['source_invoice_id'] === $sourceInvoice->id
                    && $context['destination_invoice_id'] === $destinationInvoice->id
                    && $context['source_status_before'] === 'paid'
                    && $context['source_status_after'] === 'sent'
                    && $context['destination_status_before'] === 'sent'
                    && $context['destination_status_after'] === 'paid'
                    && $context['reattribute_reason'] === 'Later payment belonged to the newer invoice.'
                    && $context['shown_as_reattributed_out'] === true
                    && $context['shown_as_reattributed_in'] === true;
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

    public function test_non_owner_cannot_reattribute_payment(): void
    {
        [$owner, , $sourceInvoice] = $this->makeInvoice(['number' => 'INV-REATTR-FORBID-SRC']);
        [, , $destinationInvoice] = $this->makeInvoice(['number' => 'INV-REATTR-FORBID-DEST'], $owner);
        $other = User::factory()->create();

        $payment = InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'txid' => 'tx-forbidden-reattribute',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $this->actingAs($other)
            ->patch(route('invoices.payments.reattribute', [$sourceInvoice, $payment]), [
                'destination_invoice_id' => $destinationInvoice->id,
                'reattribute_reason' => 'Not my invoice',
            ])
            ->assertForbidden();

        $payment->refresh();
        $this->assertSame($sourceInvoice->id, $payment->accounting_invoice_id);
        $this->assertNull($payment->reattributed_at);
    }

    public function test_reattribution_requires_destination_invoice_owned_by_the_same_owner(): void
    {
        [$owner, , $sourceInvoice] = $this->makeInvoice(['number' => 'INV-REATTR-OWNER-SRC']);
        $otherOwner = User::factory()->create(['email' => 'other-owner@example.com']);
        [, , $foreignDestination] = $this->makeInvoice(['number' => 'INV-REATTR-FOREIGN'], $otherOwner);

        $payment = InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'txid' => 'tx-foreign-destination',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.reattribute', [$sourceInvoice, $payment]), [
                'correction_payment_id' => $payment->id,
                'destination_invoice_id' => $foreignDestination->id,
                'reattribute_reason' => 'Should fail',
            ])
            ->assertSessionHasErrors('destination_invoice_id');

        $payment->refresh();
        $this->assertSame($sourceInvoice->id, $payment->accounting_invoice_id);
        $this->assertNull($payment->reattributed_at);
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

    public function test_payment_reattribution_returns_404_for_mismatched_invoice_payment_pair(): void
    {
        [$owner, , $invoiceA] = $this->makeInvoice(['number' => 'INV-REATTR-A']);
        [, , $invoiceB] = $this->makeInvoice(['number' => 'INV-REATTR-B'], $owner);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoiceB->id,
            'txid' => 'tx-reattr-wrong-parent',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.reattribute', [$invoiceA, $payment]), [
                'destination_invoice_id' => $invoiceA->id,
                'reattribute_reason' => 'Wrong pair',
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

    public function test_manual_adjustments_cannot_be_reattributed(): void
    {
        [$owner, , $sourceInvoice] = $this->makeInvoice(['number' => 'INV-MANUAL-REATTR-SRC']);
        [, , $destinationInvoice] = $this->makeInvoice(['number' => 'INV-MANUAL-REATTR-DEST'], $owner);

        $payment = InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'txid' => 'manual-reattribute-attempt',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
            'is_adjustment' => true,
        ]);

        $this->actingAs($owner)
            ->patch(route('invoices.payments.reattribute', [$sourceInvoice, $payment]), [
                'destination_invoice_id' => $destinationInvoice->id,
                'reattribute_reason' => 'Should fail',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Manual adjustments cannot be reattributed through payment corrections.');

        $payment->refresh();
        $this->assertSame($sourceInvoice->id, $payment->accounting_invoice_id);
        $this->assertNull($payment->reattributed_at);
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
