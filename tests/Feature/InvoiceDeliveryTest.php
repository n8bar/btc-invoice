<?php

namespace Tests\Feature;

use App\Jobs\DeliverInvoiceMail;
use App\Mail\InvoicePaymentAcknowledgmentClientMail;
use App\Mail\InvoiceReadyMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\InvoiceDeliveryService;
use App\Services\MailAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InvoiceDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_queue_invoice_email(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
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
        $invoice->enablePublicShare();

        $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
            'message' => 'Thanks for your business',
            'cc_self' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
            'status' => 'queued',
            'message' => 'Thanks for your business',
            'cc' => $owner->email,
        ]);

        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->first();
        Queue::assertPushed(DeliverInvoiceMail::class, function ($job) use ($delivery) {
            return $job->delivery->is($delivery);
        });
    }

    public function test_owner_can_save_delivery_message_draft(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-DRAFT',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0exampledraft',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $this
            ->actingAs($owner)
            ->patchJson(route('invoices.deliver.draft', $invoice), [
                'message' => 'Follow up tomorrow morning',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'message' => 'Follow up tomorrow morning',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'delivery_message_draft' => 'Follow up tomorrow morning',
        ]);
    }

    public function test_saving_blank_delivery_message_draft_clears_previous_value(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-DRAFT-CLEAR',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0exampledraftclear',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'delivery_message_draft' => 'Existing note',
        ]);

        $this
            ->actingAs($owner)
            ->patchJson(route('invoices.deliver.draft', $invoice), [
                'message' => '   ',
            ])
            ->assertOk()
            ->assertJson([
                'saved' => true,
                'message' => null,
            ]);

        $invoice->refresh();
        $this->assertNull($invoice->delivery_message_draft);
    }

    public function test_non_owner_cannot_save_delivery_message_draft(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-DRAFT-403',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0exampledraft403',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $this
            ->actingAs($otherUser)
            ->patchJson(route('invoices.deliver.draft', $invoice), [
                'message' => 'Unauthorized change',
            ])
            ->assertForbidden();
    }

    public function test_owner_delivery_redirects_to_getting_started_when_context_flag_present(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-GS',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplegs',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
            'message' => 'Thanks for your business',
            'getting_started' => 1,
        ]);

        $response->assertRedirect(route('getting-started.start'));
        $response->assertSessionHas('status', 'Invoice email queued.');
        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
        ]);
    }

    public function test_successful_delivery_clears_saved_delivery_message_draft(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-CLEAR-DRAFT',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplecleardraft',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
            'delivery_message_draft' => 'Draft that should clear',
        ]);
        $invoice->enablePublicShare();

        $this
            ->actingAs($owner)
            ->post(route('invoices.deliver', $invoice), [
                'message' => 'Final send note',
                'cc_self' => false,
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertNull($invoice->delivery_message_draft);
    }

    public function test_manual_send_is_skipped_when_outbound_mail_is_disabled(): void
    {
        Queue::fake();
        config(['mail.safety.outbound_enabled' => false]);

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-DISABLED',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0exampledisabled',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
            'delivery_message_draft' => 'Keep this draft',
        ]);
        $invoice->enablePublicShare();

        $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
            'message' => 'Do not send',
            'cc_self' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Outbound mail is temporarily disabled.');

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
            'status' => 'skipped',
            'recipient' => $client->email,
            'error_message' => 'Outbound mail is temporarily disabled.',
        ]);

        Queue::assertNothingPushed();

        $invoice->refresh();
        $this->assertSame('Keep this draft', $invoice->delivery_message_draft);
    }

    public function test_manual_send_is_skipped_within_cooldown_after_recent_send(): void
    {
        Queue::fake();
        config(['mail.safety.manual_send_cooldown_minutes' => 60]);

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-COOLDOWN',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplecooldown',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'sent',
            'recipient' => $client->email,
            'dispatched_at' => now()->subMinutes(5),
            'sent_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
            'message' => 'Try again too soon',
            'cc_self' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas(
            'status',
            'Invoice email skipped because the same notice was already queued or sent recently.'
        );

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
            'status' => 'skipped',
            'recipient' => $client->email,
            'error_message' => 'Invoice email skipped because the same notice was already queued or sent recently.',
        ]);

        Queue::assertNothingPushed();
        $this->assertSame(2, InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'send')->count());
    }

    public function test_manual_send_cooldown_matches_recipient_case_insensitively(): void
    {
        Queue::fake();
        config(['mail.safety.manual_send_cooldown_minutes' => 60]);

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-CASE',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplecase',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'sent',
            'recipient' => 'Billing@Example.com',
            'dispatched_at' => now()->subMinutes(5),
            'sent_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
            'message' => 'Try again too soon with lowercase recipient',
            'cc_self' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas(
            'status',
            'Invoice email skipped because the same notice was already queued or sent recently.'
        );

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
            'status' => 'skipped',
            'recipient' => $client->email,
            'error_message' => 'Invoice email skipped because the same notice was already queued or sent recently.',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_manual_send_is_skipped_when_matching_delivery_intent_is_locked(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-1001-LOCKED',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplelocked',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $deliveries = app(InvoiceDeliveryService::class);
        $lock = Cache::lock('invoice-delivery-intent:' . $deliveries->intentKey($invoice, 'send', $client->email), 10);
        $this->assertTrue($lock->get());

        try {
            $response = $this->actingAs($owner)->post(route('invoices.deliver', $invoice), [
                'message' => 'Do not double queue this',
                'cc_self' => false,
            ]);
        } finally {
            $lock->release();
        }

        $response->assertRedirect();
        $response->assertSessionHas('status', 'A matching delivery intent is already being processed.');

        $this->assertDatabaseHas('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
            'status' => 'skipped',
            'recipient' => $client->email,
            'error_message' => 'A matching delivery intent is already being processed.',
        ]);

        Queue::assertNothingPushed();
    }

    public function test_receipt_email_queued_when_invoice_paid(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['auto_receipt_emails' => true]);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-2001',
            'amount_usd' => 150,
            'btc_rate' => 30_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example2',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-auto-receipt',
            'sats_received' => 500_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $invoice->refresh()->refreshPaymentState();

        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)->where('type', 'receipt')->first();
        $this->assertNotNull($delivery);
        Queue::assertPushed(DeliverInvoiceMail::class, function ($job) use ($delivery) {
            return $job->delivery->is($delivery);
        });
    }

    public function test_owner_can_queue_client_receipt_manually_from_paid_invoice(): void
    {
        Queue::fake();

        $owner = User::factory()->create(['auto_receipt_emails' => false]);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Manual Receipt Co',
            'email' => 'manual-receipt@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-MANUAL-RECEIPT',
            'amount_usd' => 150,
            'btc_rate' => 30_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example-manual-receipt',
            'status' => 'paid',
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('invoices.deliver.receipt', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Receipt queued.');

        $delivery = InvoiceDelivery::where('invoice_id', $invoice->id)
            ->where('type', 'receipt')
            ->first();

        $this->assertNotNull($delivery);
        $this->assertSame('queued', $delivery->status);
        $this->assertSame($client->email, $delivery->recipient);

        Queue::assertPushed(DeliverInvoiceMail::class, function ($job) use ($delivery) {
            return $job->delivery->is($delivery);
        });
    }

    public function test_deliver_invoice_mail_applies_alias_converter(): void
    {
        Mail::fake();
        Queue::fake();

        config([
            'mail.aliasing.enabled' => true,
            'mail.aliasing.domain' => 'cryptozing.app',
        ]);
        app()->forgetInstance(MailAlias::class);

        $owner = User::factory()->create(['email' => 'owner@gmail.com']);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Alias',
            'email' => 'client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0example3',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'cc' => $owner->email,
            'message' => 'Alias test',
            'dispatched_at' => now(),
        ]);

        $job = new DeliverInvoiceMail($delivery);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
        ]);

        Mail::assertSent(InvoiceReadyMail::class, function (InvoiceReadyMail $mail) {
            return $mail->hasTo('client.example.com@cryptozing.app')
                && $mail->hasCc('owner.gmail.com@cryptozing.app');
        });
        Mail::assertNothingQueued();
    }

    public function test_payment_acknowledgment_delivery_sends_the_client_acknowledgment_mail(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Ack Client',
            'email' => 'ack-client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-ACK-CLIENT',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0exampleackclient',
            'status' => 'pending',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'ack-client-tx',
            'sats_received' => 200_000,
            'detected_at' => now(),
        ]);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'payment_acknowledgment_client',
            'context_key' => 'ack-client-tx',
            'status' => 'queued',
            'recipient' => $client->email,
            'meta' => [
                'txid' => 'ack-client-tx',
                'sats_received' => 200_000,
            ],
            'dispatched_at' => now(),
        ]);

        $job = new DeliverInvoiceMail($delivery);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'sent',
        ]);

        Mail::assertSent(InvoicePaymentAcknowledgmentClientMail::class);
    }

    public function test_payment_acknowledgment_delivery_skips_when_payment_no_longer_counts_on_the_invoice(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Ack Client',
            'email' => 'ack-client@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-ACK-SKIP',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0exampleackskip',
            'status' => 'pending',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'ack-skip-tx',
            'sats_received' => 200_000,
            'detected_at' => now(),
        ]);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'payment_acknowledgment_client',
            'context_key' => 'ack-skip-tx',
            'status' => 'queued',
            'recipient' => $client->email,
            'meta' => [
                'txid' => 'ack-skip-tx',
                'sats_received' => 200_000,
            ],
            'dispatched_at' => now(),
        ]);

        $payment->update([
            'ignored_at' => now(),
            'ignored_by_user_id' => $owner->id,
            'ignore_reason' => 'Wrong invoice',
        ]);

        $job = new DeliverInvoiceMail($delivery);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'skipped',
            'error_message' => 'Detected payment no longer matches an active payment on this invoice.',
        ]);

        Mail::assertNothingSent();
    }

    public function test_delivery_job_does_not_send_again_when_delivery_is_already_claimed_for_sending(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Claimed',
            'email' => 'claimed@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001-SENDING',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0examplesending',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'sending',
            'recipient' => $client->email,
            'message' => 'Already claimed test',
            'dispatched_at' => now(),
        ]);

        $job = new DeliverInvoiceMail($delivery);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'sending',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_delivery_job_skips_duplicate_queued_row_when_matching_send_is_in_progress(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Duplicate',
            'email' => 'duplicate@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001-DUPLICATE',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0exampleduplicate',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'sending',
            'recipient' => $client->email,
            'message' => 'First claimant',
            'dispatched_at' => now(),
        ]);

        $duplicate = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'message' => 'Second claimant',
            'dispatched_at' => now(),
        ]);

        $job = new DeliverInvoiceMail($duplicate);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $duplicate->id,
            'status' => 'skipped',
            'error_message' => 'A matching delivery send is already in progress.',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_delivery_job_skips_duplicate_queued_row_when_matching_send_was_already_sent(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Sent Duplicate',
            'email' => 'sent-duplicate@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001-SENT-DUPLICATE',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0examplesentduplicate',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'sent',
            'recipient' => $client->email,
            'message' => 'Already sent',
            'dispatched_at' => now()->subMinute(),
            'sent_at' => now()->subMinute(),
        ]);

        $duplicate = $invoice->deliveries()->create([
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'message' => 'Second send attempt',
            'dispatched_at' => now(),
        ]);

        $job = new DeliverInvoiceMail($duplicate);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $duplicate->id,
            'status' => 'skipped',
            'error_message' => 'A matching delivery has already been sent.',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_queued_manual_send_is_skipped_if_public_share_is_disabled_before_job_runs(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3002-REVALIDATE',
            'amount_usd' => 120,
            'btc_rate' => 30_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0examplerevalidate',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'message' => 'Revalidate test',
            'dispatched_at' => now(),
        ]);

        $invoice->forceFill([
            'public_enabled' => false,
            'public_token' => null,
        ])->save();

        $job = new DeliverInvoiceMail($delivery);
        $job->handle(app(MailAlias::class), app(InvoiceDeliveryService::class));

        $this->assertDatabaseHas('invoice_deliveries', [
            'id' => $delivery->id,
            'status' => 'skipped',
            'error_message' => 'Public share link disabled before send.',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_non_owner_cannot_queue_invoice_email(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4001',
            'amount_usd' => 100,
            'btc_rate' => 40_000,
            'amount_btc' => 0.0025,
            'payment_address' => 'tb1qq0example4',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->enablePublicShare();

        $this
            ->actingAs($otherUser)
            ->post(route('invoices.deliver', $invoice), [
                'message' => 'Attempted unauthorized send',
                'cc_self' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('invoice_deliveries', [
            'invoice_id' => $invoice->id,
            'type' => 'send',
        ]);
    }

}
