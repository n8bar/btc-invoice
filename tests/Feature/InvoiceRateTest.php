<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\BtcRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceRateTest extends TestCase
{
    use RefreshDatabase;

    private int $invoiceSequence = 0;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget(BtcRate::CACHE_KEY);
        parent::tearDown();
    }

    public function test_show_uses_cached_rate_without_network_calls(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 00:00:00', 'UTC'));
        Cache::forget(BtcRate::CACHE_KEY);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['amount_usd' => 900]);

        $cachedRate = [
            'rate_usd' => 30000.00,
            'as_of'    => Carbon::now(),
            'source'   => 'cache',
        ];

        Cache::put(BtcRate::CACHE_KEY, $cachedRate, BtcRate::TTL);

        Http::fake(fn () => throw new \RuntimeException('HTTP should not be called when cache is warm.'));

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSeeText('30000.00');
        $response->assertSeeText('0.03');
        $response->assertSee(
            'data-utc-ts="' . $cachedRate['as_of']->copy()->utc()->toIso8601String() . '"',
            false
        );
    }

    public function test_refresh_rate_fetches_new_rate_and_updates_show_output(): void
    {
        Cache::forget(BtcRate::CACHE_KEY);
        Carbon::setTestNow(Carbon::parse('2025-01-02 10:00:00', 'UTC'));

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['amount_usd' => 900]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 30000.00,
            'as_of'    => Carbon::parse('2025-01-01 00:00:00', 'UTC'),
            'source'   => 'cache',
        ], BtcRate::TTL);

        Http::fake([
            'https://api.coinbase.com/*' => Http::response([
                'data' => ['amount' => '60000.00'],
            ], 200),
        ]);

        $this
            ->actingAs($owner)
            ->post(route('invoices.rate.refresh'))
            ->assertRedirect();

        $this->assertEquals(60000.00, Cache::get(BtcRate::CACHE_KEY)['rate_usd']);
        $this->assertTrue(
            Cache::get(BtcRate::CACHE_KEY)['as_of']->equalTo(Carbon::now()),
            'Cache should contain the refreshed timestamp.'
        );

        Http::fake(fn () => throw new \RuntimeException('Show should use cached rate after refresh.'));

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSeeText('60000.00');
        $response->assertSeeText('0.015');
        $response->assertSee(
            'data-utc-ts="' . Carbon::now()->utc()->toIso8601String() . '"',
            false
        );
    }

    public function test_stale_cached_rate_fetches_fresh_value(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-03 12:00:00', 'UTC'));
        Cache::forget(BtcRate::CACHE_KEY);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['amount_usd' => 1000]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 10000.00,
            'as_of'    => Carbon::now()->subSeconds(BtcRate::TTL + 10),
            'source'   => 'cache',
        ], BtcRate::TTL);

        Http::fake([
            'https://api.coinbase.com/*' => Http::response([
                'data' => ['amount' => '20000.00'],
            ], 200),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSeeText('20000.00');
        $response->assertSeeText('0.05');

        Http::assertSentCount(1);
    }

    public function test_show_handles_rate_fetch_failure_gracefully(): void
    {
        Cache::forget(BtcRate::CACHE_KEY);
        Http::fake([
            'https://api.coinbase.com/*' => Http::response(null, 500),
        ]);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'amount_usd' => 150,
            'amount_btc' => 0.005,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertDontSee('Rate as of');
        $response->assertSeeText('BTC rate (USD/BTC)');
        $response->assertSeeText('—');
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
            'description' => 'Consulting services',
            'amount_usd' => 900,
            'btc_rate' => 50000,
            'amount_btc' => 0.018,
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
