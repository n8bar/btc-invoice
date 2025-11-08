<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
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
        ]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 40000.00,
            'as_of'    => Carbon::now(),
            'source'   => 'cache',
        ], BtcRate::TTL);

        $expectedUri = $invoice->bitcoinUriForAmount(round(320 / 40000, 8));
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
