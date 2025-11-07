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

class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    private int $invoiceSequence = 0;

    public function test_owner_can_enable_public_share_with_expiry_preset(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);

        $response = $this
            ->actingAs($owner)
            ->patch(route('invoices.share.enable', $invoice), [
                'expires_preset' => '24h',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Public link enabled.');

        $invoice->refresh();

        $this->assertTrue($invoice->public_enabled);
        $this->assertNotNull($invoice->public_token);
        $this->assertNotNull($invoice->public_url);
        $this->assertTrue(
            $invoice->public_expires_at->equalTo(Carbon::now()->addDay())
        );

        Carbon::setTestNow();
    }

    public function test_owner_can_disable_public_share(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);
        $invoice->enablePublicShare();
        $originalToken = $invoice->public_token;

        $response = $this
            ->actingAs($owner)
            ->patch(route('invoices.share.disable', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Public link disabled.');

        $invoice->refresh();

        $this->assertFalse($invoice->public_enabled);
        $this->assertNull($invoice->public_expires_at);
        $this->assertSame($originalToken, $invoice->public_token, 'Token should remain for quick re-enable.');
    }

    public function test_owner_can_rotate_public_share_token(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);
        $invoice->enablePublicShare();
        $originalToken = $invoice->public_token;

        $response = $this
            ->actingAs($owner)
            ->patch(route('invoices.share.rotate', $invoice));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Public link regenerated.');

        $invoice->refresh();

        $this->assertTrue($invoice->public_enabled);
        $this->assertNotSame($originalToken, $invoice->public_token);
        $response->assertSessionHas('public_url', $invoice->public_url);
    }

    public function test_non_owner_cannot_toggle_public_share(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = $this->makeInvoice($owner);

        $this
            ->actingAs($otherUser)
            ->patch(route('invoices.share.enable', $invoice), [
                'expires_preset' => '24h',
            ])
            ->assertForbidden();
    }

    public function test_public_print_sets_noindex_header_when_link_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-01-01 12:00:00', 'UTC'));
        Cache::forget(BtcRate::CACHE_KEY);

        Http::fake([
            'https://api.coinbase.com/*' => Http::response([
                'data' => ['amount' => '45000.00'],
            ], 200),
        ]);

        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'public_enabled' => true,
            'public_token' => 'tok_123',
            'public_expires_at' => Carbon::now()->addDay(),
        ]);

        $response = $this->get(route('invoices.public-print', ['token' => 'tok_123']));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->assertSee($invoice->number);
        $response->assertSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);

        Carbon::setTestNow();
    }

    public function test_private_print_view_does_not_include_noindex_meta(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.print', $invoice));

        $response->assertOk();
        $response->assertHeaderMissing('X-Robots-Tag');
        $response->assertDontSee('<meta name="robots" content="noindex,nofollow,noarchive">', false);
    }

    public function test_public_print_returns_404_when_share_expired(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, [
            'public_enabled' => true,
            'public_token' => 'tok_expired',
            'public_expires_at' => Carbon::now()->subMinute(),
        ]);

        $this->get(route('invoices.public-print', ['token' => 'tok_expired']))
            ->assertNotFound();
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
            'amount_usd' => 100,
            'btc_rate' => 50000,
            'amount_btc' => 0.002,
            'btc_address' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0p7',
            'status' => 'draft',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create($defaults);

        if (!empty($overrides)) {
            $invoice->forceFill($overrides)->save();
        }

        return $invoice;
    }
}
