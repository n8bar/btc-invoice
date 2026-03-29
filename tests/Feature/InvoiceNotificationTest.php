<?php

namespace Tests\Feature;

use App\Events\InvoicePaid;
use App\Models\Client;
use App\Models\Invoice;
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
            'type' => 'owner_paid_notice',
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
            'type' => 'owner_paid_notice',
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
            'type' => 'owner_paid_notice',
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
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'owner_overpay_alert',
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
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'owner_underpay_alert',
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
            'type' => 'owner_underpay_alert',
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
            'type' => 'owner_overpay_alert',
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
            'type' => 'past_due_owner',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'past_due_client',
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
