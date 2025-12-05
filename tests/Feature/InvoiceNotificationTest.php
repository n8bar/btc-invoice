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

    public function test_owner_paid_notice_and_receipt_dispatched(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['auto_receipt_emails' => true]);
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
            'type' => 'receipt',
        ]);

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'owner_paid_notice',
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
