<?php

namespace Tests\Feature;

use App\Jobs\DeliverInvoiceMail;
use App\Mail\InvoiceReadyMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Services\MailAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $job->handle(app(MailAlias::class));

        Mail::assertQueued(InvoiceReadyMail::class, function (InvoiceReadyMail $mail) {
            return $mail->hasTo('client.example.com@cryptozing.app')
                && $mail->hasCc('owner.gmail.com@cryptozing.app');
        });
    }
}
