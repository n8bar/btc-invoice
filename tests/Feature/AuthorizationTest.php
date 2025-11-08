<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_owner_cannot_view_client(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $response = $this->actingAs($other)->get(route('clients.show', $client));

        $response->assertForbidden();
        $response->assertSee("Sorry, you don't have permission", false);
    }

    public function test_non_owner_cannot_view_invoice(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
            'notes' => null,
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-0001',
            'invoice_date' => now()->toDateString(),
            'description' => 'Consulting services',
            'amount_usd' => 100,
            'btc_rate' => 50000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0zz',
            'status' => 'draft',
            'due_date' => now()->addDays(7)->toDateString(),
            'paid_at' => null,
            'txid' => null,
        ]);

        $response = $this->actingAs($other)->get(route('invoices.show', $invoice));

        $response->assertForbidden();
        $response->assertSee("Sorry, you don't have permission", false);
    }
}
