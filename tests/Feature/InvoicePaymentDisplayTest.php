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
use Tests\TestCase;

class InvoicePaymentDisplayTest extends TestCase
{
    use RefreshDatabase;

    private int $invoiceSequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget(BtcRate::CACHE_KEY);
        parent::tearDown();
    }

    public function test_show_displays_bip21_link_and_qr_copy_controls(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-05 15:00:00', 'UTC'));

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'description' => 'BTC Consulting',
            'amount_usd' => 320,
            'btc_rate' => 40000,
            'amount_btc' => round(320 / 40000, 8),
        ]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40000.00,
            'as_of'    => Carbon::now(),
            'source'   => 'cache',
        ], BtcRate::TTL);

        $expectedUri = $invoice->bitcoinUriForAmount((float) $invoice->amount_btc);
        $escapedUri = e($expectedUri);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('href="' . $escapedUri . '"', false);
        $response->assertSee('data-copy-text="' . $escapedUri . '"', false);
        $response->assertSeeText('0.008');
        $response->assertSee('Payment QR', false);
        $response->assertSee('Thank&nbsp;you!', false);
    }

    public function test_print_view_contains_qr_and_wallet_prompt(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'amount_btc' => 0.01234567,
        ]);
        $invoice->forceFill([
            'txid' => 'abc123',
            'payment_amount_sat' => 1_234_567,
            'payment_confirmations' => 2,
            'payment_confirmed_height' => 830000,
            'payment_detected_at' => Carbon::parse('2025-01-02 12:00:00', 'UTC'),
            'payment_confirmed_at' => Carbon::parse('2025-01-02 12:10:00', 'UTC'),
        ])->save();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice));

        $response->assertOk();
        $response->assertSee('<svg', false);
        $response->assertSee('Scan with any Bitcoin wallet.', false);
        $response->assertSee('Paid amount (BTC)', false);
        $response->assertSee('0.01234567', false);
    }

    public function test_print_view_uses_billing_overrides_and_footer_note(): void
    {
        $owner = User::factory()->create([
            'branding_heading' => 'CryptoZing Invoice',
            'billing_name' => 'CryptoZing LLC',
            'billing_email' => 'hello@cryptozing.app',
            'billing_address' => "123 Main St\nDenver, CO 80202",
            'invoice_footer_note' => 'Net 7',
        ]);
        $invoice = $this->makeInvoice($owner, [
            'branding_heading_override' => 'Custom Studio Invoice',
            'billing_name_override' => 'Custom Studio',
            'billing_email_override' => 'studio@example.com',
            'billing_address_override' => "742 Evergreen Terrace\nSpringfield, USA",
            'invoice_footer_note_override' => 'Send BTC only.',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice));

        $response->assertSee('Custom Studio Invoice', false);
        $response->assertSee('Custom Studio', false);
        $response->assertSee('studio@example.com', false);
        $response->assertSee('Send BTC only.', false);
        $response->assertSee('742 Evergreen Terrace', false);
    }

    public function test_print_view_displays_client_overpayment_alert_when_above_threshold(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
        ]);

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $overpaySats = (int) round($expectedSats * 1.2); // 20% over

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-overpay',
            'sats_received' => $overpaySats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 120.00,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice->fresh('payments')));

        $response->assertSee('overpaid by approximately', false);
        $response->assertSee('Overpayments are treated as gratuities by default', false);
    }

    public function test_print_view_displays_client_underpayment_alert_when_above_threshold(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
        ]);

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $partialSats = (int) round($expectedSats * 0.6); // 40% outstanding

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-underpay',
            'sats_received' => $partialSats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 40_000,
            'fiat_amount' => 120.00,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice->fresh('payments')));

        $response->assertSee('An outstanding balance of roughly', false);
    }

    public function test_payment_metadata_fields_render_when_present(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);
        $invoice->forceFill([
            'payment_amount_sat' => 1_500_000,
            'payment_confirmations' => 3,
            'payment_confirmed_height' => 840123,
            'payment_detected_at' => Carbon::parse('2025-01-03 01:00:00', 'UTC'),
            'payment_confirmed_at' => Carbon::parse('2025-01-03 01:15:00', 'UTC'),
        ])->save();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('Paid amount (BTC)', false);
        $response->assertSee('0.015', false);
        $response->assertSee('Confirmations', false);
        $response->assertSee('3', false);
        $response->assertSee('Confirmation height', false);
        $response->assertSee('840123', false);
        $response->assertSee('Detected at', false);
        $response->assertSee('Confirmed at', false);
    }

    public function test_show_displays_billing_details_and_footer_note(): void
    {
        $owner = User::factory()->create([
            'branding_heading' => 'CryptoZing Invoice',
            'billing_name' => 'CryptoZing LLC',
            'billing_email' => 'owner@example.com',
            'billing_phone' => '555-1234',
            'billing_address' => "123 Main St\nDenver, CO",
            'invoice_footer_note' => 'Net 7 terms apply.',
        ]);

        $invoice = $this->makeInvoice($owner, [
            'branding_heading_override' => null,
            'billing_name_override' => 'Custom Studio',
            'invoice_footer_note_override' => 'Send BTC only to this address.',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertSee('CryptoZing Invoice', false);
        $response->assertSee('Custom Studio', false);
        $response->assertSee('Send BTC only to this address.', false);
    }

    public function test_partial_invoice_displays_payment_history_and_outstanding(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'status' => 'partial',
            'amount_btc' => 0.01,
        ]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 50_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-partial-1',
            'sats_received' => 400_000,
            'detected_at' => Carbon::parse('2025-01-04 10:00:00', 'UTC'),
            'confirmed_at' => Carbon::parse('2025-01-04 10:15:00', 'UTC'),
            'usd_rate' => 38_000,
            'fiat_amount' => 152.00,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice->fresh('payments')));

        $response->assertSee('Payment history', false);
        $response->assertSee('tx-partial-1', false);
        $response->assertSee('Expected', false);
        $response->assertSee('$500.00', false);
        $response->assertSee('(0.01 BTC)', false);
        $response->assertSee('Received', false);
        $response->assertSee('$152.00', false);
        $response->assertSee('Confirmed (counts toward status)', false);
        $response->assertSee('Outstanding balance', false);
        $response->assertSee('$348.00', false);
        $response->assertSee('(0.00696 BTC)', false);
        $detectedDisplay = optional(
            $invoice->fresh('payments')->payments->max('detected_at')
        )
            ?->copy()
            ->timezone(config('app.timezone'))
            ->toDayDateTimeString();
        $response->assertSee('Last payment detected', false);
        $response->assertSee($detectedDisplay, false);
    }

    public function test_bitcoin_uri_targets_outstanding_balance(): void
    {
        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'status' => 'sent',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-partial-qr',
            'sats_received' => 200_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 40_000,
            'fiat_amount' => 80.00,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice->fresh('payments')));

        // Outstanding USD = 120 => 0.003 BTC at $40k
        $response->assertSee('amount=0.003', false);
        $response->assertSee('$80.00', false);
        $response->assertSee('$120.00', false);
    }

    public function test_outstanding_balance_zeroed_within_tolerance(): void
    {
        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 45_000,
            'as_of' => Carbon::now(),
            'source' => 'test',
        ], BtcRate::TTL);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'status' => 'sent',
            'amount_usd' => 225,
            'btc_rate' => 45_000,
            'amount_btc' => 0.005,
        ]);

        $expectedSats = (int) round($invoice->amount_btc * Invoice::SATS_PER_BTC);
        $partialSats = $expectedSats - 50;
        $fiatAmount = round(($partialSats / Invoice::SATS_PER_BTC) * 45_000, 2);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-tolerance',
            'sats_received' => $partialSats,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
            'usd_rate' => 45_000,
            'fiat_amount' => $fiatAmount,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice->fresh('payments')));

        $response->assertSeeInOrder(['Outstanding balance', '$0.02'], false);
        $response->assertSee('Resolve small balance', false);
    }

    public function test_owner_can_update_payment_note(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'status' => 'sent',
        ]);

        $payment = InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-note-1',
            'sats_received' => 100_000,
            'detected_at' => Carbon::parse('2025-01-05 12:00:00', 'UTC'),
            'usd_rate' => 35_000,
            'fiat_amount' => 35.00,
        ]);

        $this
            ->actingAs($owner)
            ->patch(route('invoices.payments.note', [$invoice, $payment]), [
                'note' => 'First partial from client',
                'source_payment_id' => $payment->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $payment->id,
            'note' => 'First partial from client',
        ]);

        $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice->fresh('payments')))
            ->assertSee('First partial from client', false)
            ->assertSee('@ $35,000.00 USD/BTC', false);
    }

    public function test_print_view_shows_payment_history_table(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'status' => 'paid',
            'amount_btc' => 0.012,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-print-1',
            'sats_received' => 600_000,
            'detected_at' => Carbon::parse('2025-01-05 08:00:00', 'UTC'),
            'usd_rate' => 36_000,
            'fiat_amount' => 216.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-print-2',
            'sats_received' => 600_000,
            'detected_at' => Carbon::parse('2025-01-05 09:00:00', 'UTC'),
            'usd_rate' => 36_500,
            'fiat_amount' => 219.00,
        ]);

        $invoice->refresh()->refreshPaymentState();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice->fresh('payments')));

        $response->assertSee('Payment history', false);
        $response->assertSee('tx-print-1', false);
        $response->assertSee('tx-print-2', false);
    }

    private function makeInvoice(User $owner, array $overrides = []): Invoice
    {
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $this->invoiceSequence++;

        $defaults = [
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-' . str_pad((string) $this->invoiceSequence, 4, '0', STR_PAD_LEFT),
            'description' => 'General services',
            'amount_usd' => 500,
            'btc_rate' => 50000,
            'amount_btc' => 0.01,
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0zz',
            'status' => 'draft',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create($defaults);

        if (!empty($overrides)) {
            $invoice->forceFill($overrides)->save();
        }

        return $invoice->refresh();
    }
}
