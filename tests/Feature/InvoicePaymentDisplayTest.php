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

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice));

        $response->assertOk();
        $response->assertSee('<svg', false);
        $response->assertSee('Scan with any Bitcoin wallet.', false);
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
            'btc_address' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0p7',
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
