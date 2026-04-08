<?php

namespace Tests\Feature;

use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\InvoiceAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_paid_notice_dispatches_when_invoice_paid(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Test',
            'email' => 'client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        event(new InvoicePaid($invoice));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_paid_notice',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'receipt',
        ]);
    }

    public function test_owner_paid_notice_dispatches_even_when_client_email_is_missing(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Test',
            'email' => '',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001B',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example-b',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
        ]);

        event(new InvoicePaid($invoice));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_paid_notice',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'receipt',
        ]);
    }

    public function test_invoice_paid_event_does_not_auto_queue_client_receipt_when_multiple_active_payments_exist(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Test',
            'email' => 'client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001C',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example-c',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-auto-hold-1',
            'sats_received' => 250_000,
            'detected_at' => Carbon::now()->subMinutes(10),
            'confirmed_at' => Carbon::now()->subMinutes(10),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-auto-hold-2',
            'sats_received' => 250_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        event(new InvoicePaid($invoice->fresh(['client', 'user', 'payments', 'sourcePayments', 'deliveries'])));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_paid_notice',
            'status' => 'queued',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'receipt',
        ]);
    }

    public function test_invoice_paid_event_does_not_auto_queue_client_receipt_when_correction_state_touches_history(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Test',
            'email' => 'client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001D',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example-d',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-auto-hold-ignored-active',
            'sats_received' => 500_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-auto-hold-ignored',
            'sats_received' => 100_000,
            'detected_at' => Carbon::now()->subMinutes(5),
            'confirmed_at' => Carbon::now()->subMinutes(5),
            'ignored_at' => Carbon::now()->subMinute(),
            'ignored_by_user_id' => $owner->id,
            'ignore_reason' => 'Wrong invoice',
        ]);

        event(new InvoicePaid($invoice->fresh(['client', 'user', 'payments', 'sourcePayments', 'deliveries'])));

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'receipt',
        ]);
    }

    public function test_overpayment_alert_sent_to_client_and_owner(): void
    {
        Queue::fake();

        [$invoice] = $this->makeInvoiceWithClient();

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $overpaySats = (int) round($expectedSats * 1.2);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-overpay',
            'sats_received' => $overpaySats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $invoice->refresh()->refreshPaymentState();

        app(InvoiceAlertService::class)->checkPaymentThresholds($invoice->fresh('payments'));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_overpay_alert',
            'context_key' => 'tx-overpay',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_overpay_alert',
            'context_key' => 'tx-overpay',
        ]);
    }

    public function test_underpayment_alert_sent_to_client_and_owner(): void
    {
        Queue::fake();
        [$invoice] = $this->makeInvoiceWithClient();

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $partialSats = (int) round($expectedSats * 0.6);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-underpay',
            'sats_received' => $partialSats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $invoice->refresh()->refreshPaymentState();

        app(InvoiceAlertService::class)->checkPaymentThresholds($invoice->fresh('payments'));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_underpay_alert',
            'context_key' => 'tx-underpay',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'issuer_underpay_alert',
            'context_key' => 'tx-underpay',
        ]);
    }

    public function test_resolving_small_balance_marks_paid_and_skips_underpay_nags(): void
    {
        Queue::fake();
        Cache::put(\App\Services\BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ]);

        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();
        $invoice->forceFill([
            'status' => 'sent',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
        ])->save();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-partial',
            'sats_received' => (int) round((199 / 40_000) * Invoice::SATS_PER_BTC),
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 40_000,
            'fiat_amount' => 199,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $clientDelivery = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'client_underpay_alert',
            'status' => 'queued',
            'recipient' => $client->email,
            'dispatched_at' => now(),
        ]);

        $ownerDelivery = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'issuer_underpay_alert',
            'status' => 'queued',
            'recipient' => $owner->email,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('invoices.payments.adjustments.resolve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0, $invoice->outstanding_sats);

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $clientDelivery->id,
            'status' => 'skipped',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $ownerDelivery->id,
            'status' => 'skipped',
        ]);
    }

    public function test_resolving_overpayment_skips_queued_overpay_alerts(): void
    {
        Queue::fake();

        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $overpaySats = (int) round($expectedSats * 1.2);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-overpay-resolved',
            'sats_received' => $overpaySats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $invoice->refresh()->refreshPaymentState();

        $clientDelivery = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'client_overpay_alert',
            'status' => 'queued',
            'recipient' => $client->email,
            'dispatched_at' => now(),
        ]);

        $ownerDelivery = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'issuer_overpay_alert',
            'status' => 'queued',
            'recipient' => $owner->email,
            'dispatched_at' => now(),
        ]);

        $invoice->forceFill([
            'amount_usd' => 300,
            'amount_btc' => 0.006,
        ])->save();

        app(InvoiceAlertService::class)->skipInvalidQueuedDeliveries(
            $invoice->fresh('payments'),
            'Skipped after invoice change.'
        );

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $clientDelivery->id,
            'status' => 'skipped',
            'error_message' => 'Skipped after invoice change. Overpayment alert no longer applies.',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $ownerDelivery->id,
            'status' => 'skipped',
            'error_message' => 'Skipped after invoice change. Overpayment alert no longer applies.',
        ]);
    }

    public function test_past_due_command_sends_alerts(): void
    {
        Queue::fake();
        [$invoice] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(3)->toDateString(),
        ])->save();

        $this->artisan('invoices:send-past-due-alerts')->assertExitCode(0);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_issuer',
            'context_key' => 'past_due_1',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_client',
            'context_key' => 'past_due_1',
        ]);
    }

    public function test_past_due_slot_not_resent_after_already_queued(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(3)->toDateString(),
        ])->save();

        // Simulate slot 1 already queued
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_issuer',
            'status' => 'queued',
            'recipient' => $owner->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subHour(),
        ]);

        $this->artisan('invoices:send-past-due-alerts')->assertExitCode(0);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_issuer',
            'status' => 'queued',
            'context_key' => 'past_due_2',
        ]);
    }

    public function test_past_due_slot_2_not_sent_before_day_7(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(3)->toDateString(),
        ])->save();

        // Simulate slot 1 already sent
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_issuer',
            'status' => 'sent',
            'recipient' => $owner->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(3),
        ]);
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_client',
            'status' => 'sent',
            'recipient' => $client->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(3),
        ]);

        $this->artisan('invoices:send-past-due-alerts')->assertExitCode(0);

        // Day 3 is past threshold for slot 1 (already sent) but not slot 2 (needs day 7)
        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'context_key' => 'past_due_2',
        ]);
    }

    public function test_past_due_slot_2_fires_after_day_7_when_slot_1_sent(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(8)->toDateString(),
        ])->save();

        // Simulate slot 1 already sent
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_issuer',
            'status' => 'sent',
            'recipient' => $owner->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(7),
        ]);
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_client',
            'status' => 'sent',
            'recipient' => $client->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(7),
        ]);

        $this->artisan('invoices:send-past-due-alerts')->assertExitCode(0);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_issuer',
            'context_key' => 'past_due_2',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_client',
            'context_key' => 'past_due_2',
            'status' => 'queued',
        ]);
    }

    public function test_past_due_catchup_sends_only_one_slot_per_run(): void
    {
        Queue::fake();
        [$invoice] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(15)->toDateString(),
        ])->save();

        // No past-due deliveries yet — invoice has been overdue 15 days unnoticed
        $this->artisan('invoices:send-past-due-alerts')->assertExitCode(0);

        // Only slot 1 should fire on first run, not slot 2 or 3
        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'context_key' => 'past_due_1',
            'status' => 'queued',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'context_key' => 'past_due_2',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'context_key' => 'past_due_3',
        ]);
    }

    public function test_alert_skipped_when_failed_delivery_exists_for_same_trigger(): void
    {
        Queue::fake();
        [$invoice] = $this->makeInvoiceWithClient();

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $partialSats = (int) round($expectedSats * 0.6);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-underpay-failed',
            'sats_received' => $partialSats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $invoice->refresh()->refreshPaymentState();

        // Simulate a previously failed delivery for the same txid trigger
        $invoice->deliveries()->create([
            'user_id' => $invoice->user_id,
            'type' => 'client_underpay_alert',
            'status' => 'failed',
            'recipient' => 'client@example.com',
            'context_key' => 'tx-underpay-failed',
            'dispatched_at' => now()->subHour(),
        ]);

        app(InvoiceAlertService::class)->checkPaymentThresholds($invoice->fresh('payments'));

        // The deliveryExists() guard fires before queue() — no new row is created at all.
        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_underpay_alert',
            'status' => 'queued',
        ]);

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'client_underpay_alert',
            'status' => 'skipped',
        ]);
    }

    // -------------------------------------------------------------------------
    // Past-due delivery deduplication fix tests
    // -------------------------------------------------------------------------

    /**
     * Test A — A second sendPastDueAlerts() call against an invoice that already
     * has all 3 past-due slots with status = 'sent' creates no new InvoiceDelivery rows.
     */
    public function test_past_due_second_call_creates_no_rows_when_all_slots_already_sent(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(20)->toDateString(),
        ])->save();

        // Seed all 6 delivery rows (issuer + client × 3 slots) as 'sent'
        foreach (['past_due_1', 'past_due_2', 'past_due_3'] as $contextKey) {
            $invoice->deliveries()->create([
                'user_id' => $owner->id,
                'type' => 'past_due_issuer',
                'status' => 'sent',
                'recipient' => $owner->email,
                'context_key' => $contextKey,
                'dispatched_at' => now()->subDays(5),
            ]);
            $invoice->deliveries()->create([
                'user_id' => $owner->id,
                'type' => 'past_due_client',
                'status' => 'sent',
                'recipient' => $client->email,
                'context_key' => $contextKey,
                'dispatched_at' => now()->subDays(5),
            ]);
        }

        $countBefore = InvoiceDelivery::count();

        app(InvoiceAlertService::class)->sendPastDueAlerts($invoice->fresh(['client', 'user', 'deliveries']));

        $this->assertSame($countBefore, InvoiceDelivery::count());
    }

    /**
     * Test B — A fresh past-due invoice (no existing deliveries) produces a
     * 'queued' delivery row for slot 1 on the first sendPastDueAlerts() call.
     */
    public function test_past_due_first_call_queues_slot_1_for_fresh_invoice(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(2)->toDateString(),
        ])->save();

        app(InvoiceAlertService::class)->sendPastDueAlerts($invoice->fresh(['client', 'user', 'deliveries']));

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_issuer',
            'context_key' => 'past_due_1',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_client',
            'context_key' => 'past_due_1',
            'status' => 'queued',
        ]);
    }

    /**
     * Test C — When slot 1 is already 'sent' but slot 2 has no existing row and
     * the invoice is 7+ days past due, sendPastDueAlerts() queues slot 2, not slot 1 again.
     */
    public function test_past_due_queues_slot_2_not_slot_1_when_slot_1_already_sent_and_7_days_past_due(): void
    {
        Queue::fake();
        [$invoice, $owner, $client] = $this->makeInvoiceWithClient();

        $invoice->forceFill([
            'status' => 'sent',
            'due_date' => now()->subDays(8)->toDateString(),
        ])->save();

        // Slot 1 already sent for both recipients
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_issuer',
            'status' => 'sent',
            'recipient' => $owner->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(7),
        ]);
        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'past_due_client',
            'status' => 'sent',
            'recipient' => $client->email,
            'context_key' => 'past_due_1',
            'dispatched_at' => now()->subDays(7),
        ]);

        app(InvoiceAlertService::class)->sendPastDueAlerts($invoice->fresh(['client', 'user', 'deliveries']));

        // Slot 2 should be queued
        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_issuer',
            'context_key' => 'past_due_2',
            'status' => 'queued',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_client',
            'context_key' => 'past_due_2',
            'status' => 'queued',
        ]);

        // No additional slot 1 row should exist beyond the two 'sent' rows seeded above
        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'context_key' => 'past_due_1',
            'status' => 'queued',
        ]);
    }

    private function makeInvoiceWithClient(): array
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-2001',
            'amount_usd' => 250,
            'btc_rate' => 50_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        return [$invoice, $owner, $client];
    }
}
