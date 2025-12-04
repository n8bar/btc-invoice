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
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_snapshot_for_owner(): void
    {
        Cache::forget(BtcRate::CACHE_KEY);
        Carbon::setTestNow(Carbon::parse('2025-01-02 12:00:00', config('app.timezone')));

        $owner = User::factory()->create();
        $client = $this->makeClient($owner, 'Acme');

        $sent = $this->makeInvoice($owner, $client, [
            'status' => 'sent',
            'amount_usd' => 500,
            'btc_rate' => 50_000,
            'amount_btc' => 0.01,
            'due_date' => Carbon::today(config('app.timezone'))->addDay(),
        ]);

        $partial = $this->makeInvoice($owner, $client, [
            'status' => 'partial',
            'amount_usd' => 400,
            'btc_rate' => 40_000,
            'amount_btc' => 0.01,
            'due_date' => Carbon::today(config('app.timezone'))->subDay(),
        ]);

        $this->makeInvoice($owner, $client, [
            'status' => 'paid',
            'amount_usd' => 999,
        ]);

        InvoicePayment::create([
            'invoice_id' => $partial->id,
            'txid' => 'tx-partial-1',
            'sats_received' => 200_000_000, // 0.002 BTC
            'usd_rate' => 40_000,
            'fiat_amount' => 200.00,
            'detected_at' => Carbon::now()->subDays(2),
            'confirmed_at' => Carbon::now()->subDays(2),
        ]);

        // Foreign data should be ignored
        $otherUser = User::factory()->create();
        $otherClient = $this->makeClient($otherUser, 'Other Co');
        $otherInvoice = $this->makeInvoice($otherUser, $otherClient, [
            'status' => 'sent',
            'amount_usd' => 10_000,
        ]);
        InvoicePayment::create([
            'invoice_id' => $otherInvoice->id,
            'txid' => 'tx-other',
            'sats_received' => 100_000_000,
            'usd_rate' => 30_000,
            'detected_at' => Carbon::now(),
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Outstanding (USD)', false);
        $response->assertSee('$700.00', false); // 500 + (400-200)
        $response->assertSee('Open invoices', false);
        $response->assertSee('2', false);
        $response->assertSee('Past due', false);
        $response->assertSee('1', false);
        $response->assertSee('$200.00', false); // recent payment
        $response->assertSee($partial->number, false);
        $response->assertDontSee('tx-other', false);

        Carbon::setTestNow();
    }

    public function test_dashboard_respects_soft_deletes_and_recent_payment_logic(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-05 09:00:00', config('app.timezone')));

        $owner = User::factory()->create();
        $client = $this->makeClient($owner, 'Acme Two');

        $paidInvoice = $this->makeInvoice($owner, $client, [
            'status' => 'paid',
            'amount_usd' => 250,
            'btc_rate' => 25_000,
            'amount_btc' => 0.01,
        ]);

        InvoicePayment::create([
            'invoice_id' => $paidInvoice->id,
            'txid' => 'tx-paid-1',
            'sats_received' => 1_000_000, // 0.01 BTC at 25k -> $250
            'usd_rate' => 25_000,
            'detected_at' => null,
            'created_at' => Carbon::now()->subDays(1),
            'confirmed_at' => Carbon::now()->subDays(1),
        ]);

        $openInvoice = $this->makeInvoice($owner, $client, [
            'status' => 'sent',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'due_date' => Carbon::today(config('app.timezone'))->subDay(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $openInvoice->id,
            'txid' => 'tx-open-1',
            'sats_received' => 100_000, // $50
            'usd_rate' => 50_000,
            'detected_at' => Carbon::now()->subDays(2),
            'confirmed_at' => Carbon::now()->subDays(2),
        ]);

        $trashed = $this->makeInvoice($owner, $client, [
            'status' => 'sent',
            'amount_usd' => 999,
            'btc_rate' => 30_000,
            'amount_btc' => 0.0333,
        ]);
        $trashed->delete();

        InvoicePayment::create([
            'invoice_id' => $trashed->id,
            'txid' => 'tx-trashed',
            'sats_received' => 100_000,
            'usd_rate' => 30_000,
            'detected_at' => Carbon::now()->subDay(),
            'confirmed_at' => Carbon::now()->subDay(),
        ]);

        $otherUser = User::factory()->create();
        $otherClient = $this->makeClient($otherUser, 'Other Co');
        $otherInvoice = $this->makeInvoice($otherUser, $otherClient, ['status' => 'sent', 'amount_usd' => 999]);
        InvoicePayment::create([
            'invoice_id' => $otherInvoice->id,
            'txid' => 'tx-foreign',
            'sats_received' => 100_000,
            'usd_rate' => 10_000,
            'detected_at' => Carbon::now()->subDay(),
            'confirmed_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->actingAs($owner)->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('2 payments', false); // includes paid invoice payment
        $response->assertSee('$300.00', false); // 250 + 50 recent total
        $response->assertSee($openInvoice->number, false);
        $response->assertSee($paidInvoice->number, false);
        $response->assertDontSee($trashed->number, false);
        $response->assertDontSee('tx-foreign', false);

        Carbon::setTestNow();
    }

    public function test_dashboard_cache_is_isolated_per_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-02-01 10:00:00', config('app.timezone')));

        $ownerA = User::factory()->create();
        $clientA = $this->makeClient($ownerA, 'Alpha');
        $invoiceA = $this->makeInvoice($ownerA, $clientA, ['status' => 'sent', 'amount_usd' => 123]);

        // Warm cache for user A
        $this->actingAs($ownerA)->get(route('dashboard'))->assertOk();

        $ownerB = User::factory()->create();
        $clientB = $this->makeClient($ownerB, 'Beta');
        $invoiceB = $this->makeInvoice($ownerB, $clientB, ['status' => 'sent', 'amount_usd' => 456]);
        InvoicePayment::create([
            'invoice_id' => $invoiceB->id,
            'txid' => 'tx-beta',
            'sats_received' => 100_000,
            'usd_rate' => 30_000,
            'detected_at' => Carbon::now()->subHour(),
            'confirmed_at' => Carbon::now()->subHour(),
        ]);

        $responseB = $this->actingAs($ownerB)->get(route('dashboard'));
        $responseB->assertOk();
        $responseB->assertSee($invoiceB->number, false);
        $responseB->assertDontSee($invoiceA->number, false);

        Carbon::setTestNow();
    }

    public function test_recent_payments_are_limited_and_ordered(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-03-01 08:00:00', config('app.timezone')));

        $owner = User::factory()->create();
        $client = $this->makeClient($owner, 'Ordering Co');

        // Create 6 invoices + payments; only 5 most recent should appear.
        $invoiceNumbers = [];
        foreach (range(1, 6) as $i) {
            $inv = $this->makeInvoice($owner, $client, ['status' => 'sent', 'amount_usd' => 10 * $i]);
            $invoiceNumbers[$i] = $inv->number;

            InvoicePayment::create([
                'invoice_id' => $inv->id,
                'txid' => 'tx-' . $i . '-' . Str::random(4),
                'sats_received' => 10_000 + $i,
                'usd_rate' => 30_000,
                'detected_at' => Carbon::now()->subHours($i),
                'confirmed_at' => Carbon::now()->subHours($i),
            ]);
        }

        $response = $this->actingAs($owner)->get(route('dashboard'));
        $response->assertOk();
        // Newest should be i=1; i=6 should be excluded.
        $response->assertSee($invoiceNumbers[1], false);
        $response->assertSee($invoiceNumbers[2], false);
        $response->assertSee($invoiceNumbers[3], false);
        $response->assertSee($invoiceNumbers[4], false);
        $response->assertSee($invoiceNumbers[5], false);
        $response->assertDontSee($invoiceNumbers[6], false);

        Carbon::setTestNow();
    }

    public function test_cache_refresh_required_within_ttl(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-04-01 12:00:00', config('app.timezone')));

        $owner = User::factory()->create();
        $client = $this->makeClient($owner, 'Cache Co');
        $invoice = $this->makeInvoice($owner, $client, ['status' => 'sent', 'amount_usd' => 50]);

        $this->actingAs($owner)->get(route('dashboard'))->assertOk(); // warm cache

        // Change data while cache is still valid
        $this->makeInvoice($owner, $client, ['status' => 'sent', 'amount_usd' => 75]);

        // Cached response should still show only the original invoice count/value
        $responseCached = $this->actingAs($owner)->get(route('dashboard'));
        $responseCached->assertOk();
        $responseCached->assertSee('$50.00', false);
        $responseCached->assertDontSee('$75.00', false);

        Cache::forget("dashboard:snapshot:user:{$owner->id}");

        $responseFresh = $this->actingAs($owner)->get(route('dashboard'));
        $responseFresh->assertOk();
        $responseFresh->assertSee('$125.00', false); // 50 + 75 outstanding

        Carbon::setTestNow();
    }

    private int $invoiceSequence = 0;

    private function makeClient(User $owner, string $name = 'Client'): Client
    {
        return Client::create([
            'user_id' => $owner->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)) . '@example.test',
        ]);
    }

    private function makeInvoice(User $owner, Client $client, array $overrides = []): Invoice
    {
        $this->invoiceSequence++;

        $defaults = [
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-' . str_pad((string) $this->invoiceSequence, 4, '0', STR_PAD_LEFT),
            'description' => 'Services',
            'amount_usd' => 500,
            'btc_rate' => 50_000,
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
