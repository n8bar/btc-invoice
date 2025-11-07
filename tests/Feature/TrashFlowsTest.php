<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrashFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_trash_lists_deleted_records_and_restore_flow(): void
    {
        $owner = User::factory()->create();
        $trashed = $this->makeClient($owner, ['name' => 'Trashed Client']);
        $active = $this->makeClient($owner, ['name' => 'Active Client']);

        $trashed->delete();

        $response = $this
            ->actingAs($owner)
            ->get(route('clients.trash'));

        $response->assertOk();
        $response->assertSee('Trashed Client');
        $response->assertDontSee('Active Client');

        $restoreResponse = $this
            ->actingAs($owner)
            ->patch(route('clients.restore', ['clientId' => $trashed->id]));

        $restoreResponse->assertRedirect(route('clients.trash'));
        $restoreResponse->assertSessionHas('status', 'Client restored.');
        $this->assertFalse($trashed->fresh()->trashed());
    }

    public function test_invoice_trash_lists_deleted_records_and_restore_flow(): void
    {
        $owner = User::factory()->create();
        $trashed = $this->makeInvoice($owner, ['number' => 'INV-TRASHED']);
        $active = $this->makeInvoice($owner, ['number' => 'INV-ACTIVE']);

        $trashed->delete();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.trash'));

        $response->assertOk();
        $response->assertSee('INV-TRASHED');
        $response->assertDontSee('INV-ACTIVE');

        $restoreResponse = $this
            ->actingAs($owner)
            ->patch(route('invoices.restore', ['invoiceId' => $trashed->id]));

        $restoreResponse->assertRedirect(route('invoices.trash'));
        $restoreResponse->assertSessionHas('status', 'Invoice restored.');
        $this->assertFalse($trashed->fresh()->trashed());
    }

    public function test_client_force_delete_is_restricted_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $client = $this->makeClient($owner);

        $this
            ->actingAs($other)
            ->delete(route('clients.force-destroy', ['clientId' => $client->id]))
            ->assertForbidden()
            ->assertSee("Sorry, you don't have permission.", false);
    }

    public function test_invoice_force_delete_is_restricted_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $invoice = $this->makeInvoice($owner);

        $this
            ->actingAs($other)
            ->delete(route('invoices.force-destroy', ['invoiceId' => $invoice->id]))
            ->assertForbidden()
            ->assertSee("Sorry, you don't have permission.", false);
    }

    private function makeClient(User $owner, array $overrides = []): Client
    {
        $defaults = [
            'user_id' => $owner->id,
            'name' => 'Client ' . uniqid(),
            'email' => 'client@example.com',
            'notes' => null,
        ];

        return Client::create(array_merge($defaults, $overrides));
    }

    private function makeInvoice(User $owner, array $overrides = []): Invoice
    {
        $client = $this->makeClient($owner);

        $defaults = [
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-' . strtoupper(uniqid()),
            'description' => 'Services',
            'amount_usd' => 150,
            'btc_rate' => 50000,
            'amount_btc' => 0.003,
            'btc_address' => 'bc1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0p7',
            'status' => 'draft',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create(array_merge($defaults, $overrides));

        return $invoice->refresh();
    }
}
