<?php

namespace Tests\Feature;

use App\Mail\InvoicePaymentAcknowledgmentClientMail;
use App\Mail\NotificationBrandingPreviewMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_acknowledgment_mail_uses_cryptozing_shared_branding(): void
    {
        $owner = User::factory()->create([
            'name' => 'Antonina Owner',
            'email' => 'antonina12@nospam.site',
        ]);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Phase 3 QA Client',
            'email' => 'ms16-phase3-qa@nospam.site',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-MAIL-BRAND',
            'description' => 'Branding proof',
            'amount_usd' => 80,
            'btc_rate' => 40_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kygt080',
            'status' => 'paid',
            'public_enabled' => true,
            'public_token' => 'mail-branding-proof',
            'public_expires_at' => Carbon::now()->addDay(),
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'accounting_invoice_id' => $invoice->id,
            'txid' => 'mail-branding-proof-tx',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
        ]);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'payment_acknowledgment_client',
            'context_key' => $payment->txid,
            'status' => 'sent',
            'recipient' => $client->email,
            'dispatched_at' => Carbon::now(),
            'sent_at' => Carbon::now(),
        ]);

        $html = (new InvoicePaymentAcknowledgmentClientMail(
            $invoice->fresh(['client', 'user']),
            $delivery,
            $payment
        ));

        $html = $html->render();

        $this->assertStringContainsString('CryptoZing', $html);
        $this->assertStringContainsString('Non-custodial bitcoin invoicing', $html);
        $this->assertStringContainsString('Create bitcoin invoices, monitor incoming payments, and send receipts with CryptoZing.', $html);
        $this->assertStringContainsString('Bitcoin payment detected', $html);
        $this->assertStringContainsString('A Bitcoin payment of', $html);
        $this->assertStringNotContainsString('for Invoice ' . $invoice->number, $html);
        $this->assertStringNotContainsString('for this invoice', $html);
        $this->assertTrue(
            str_contains($html, 'cid:')
            || str_contains($html, 'data:image/png;base64,'),
            'Expected inline email logo markup.'
        );
        $this->assertStringNotContainsString('https://cryptozing.app/images/CZ.png', $html);
        $this->assertStringNotContainsString('notification-logo.png', $html);
        $this->assertStringNotContainsString('Laravel Logo', $html);
        $this->assertSame('Bitcoin payment detected', (new InvoicePaymentAcknowledgmentClientMail(
            $invoice->fresh(['client', 'user']),
            $delivery,
            $payment
        ))->envelope()->subject);
    }

    public function test_payment_acknowledgment_mail_uses_owner_mail_branding_overrides(): void
    {
        $owner = User::factory()->create([
            'name' => 'Antonina Owner',
            'email' => 'antonina12@nospam.site',
            'mail_brand_name' => 'Phase 3 Mail',
            'mail_brand_tagline' => 'Owner-reviewed bitcoin receipts',
            'mail_footer_blurb' => 'Phase 3 custom footer blurb.',
        ]);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Phase 3 QA Client',
            'email' => 'ms16-phase3-qa@nospam.site',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-MAIL-BRAND-CUSTOM',
            'description' => 'Branding proof',
            'amount_usd' => 80,
            'btc_rate' => 40_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7kygt080',
            'status' => 'paid',
            'public_enabled' => true,
            'public_token' => 'mail-branding-proof-custom',
            'public_expires_at' => Carbon::now()->addDay(),
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'accounting_invoice_id' => $invoice->id,
            'txid' => 'mail-branding-proof-custom-tx',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
        ]);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'payment_acknowledgment_client',
            'context_key' => $payment->txid,
            'status' => 'sent',
            'recipient' => $client->email,
            'dispatched_at' => Carbon::now(),
            'sent_at' => Carbon::now(),
        ]);

        $html = (new InvoicePaymentAcknowledgmentClientMail(
            $invoice->fresh(['client', 'user']),
            $delivery,
            $payment
        ))->render();

        $this->assertStringContainsString('Phase 3 Mail', $html);
        $this->assertStringContainsString('Owner-reviewed bitcoin receipts', $html);
        $this->assertStringContainsString('Phase 3 custom footer blurb.', $html);
        $this->assertStringNotContainsString('Non-custodial bitcoin invoicing', $html);
    }

    public function test_notification_branding_preview_mail_uses_saved_branding_and_can_hide_logo(): void
    {
        $owner = User::factory()->create([
            'name' => 'Antonina Owner',
            'email' => 'antonina12@nospam.site',
            'mail_brand_name' => 'Phase 3 Mail',
            'mail_brand_tagline' => 'Owner-reviewed bitcoin receipts',
            'mail_footer_blurb' => 'Phase 3 custom footer blurb.',
            'show_mail_logo' => false,
        ]);

        $html = (new NotificationBrandingPreviewMail($owner))->render();

        $this->assertStringContainsString('Branded test message', $html);
        $this->assertStringContainsString('Phase 3 Mail', $html);
        $this->assertStringContainsString('Owner-reviewed bitcoin receipts', $html);
        $this->assertStringContainsString('Phase 3 custom footer blurb.', $html);
        $this->assertStringNotContainsString('cid:', $html);
        $this->assertStringNotContainsString('data:image/png;base64,', $html);
    }
}
