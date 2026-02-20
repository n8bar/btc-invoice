<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceShowEditFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_index_empty_state_and_action_bar_are_visible(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertSee('Create, send, and track invoice payment status.', false);
        $response->assertSee('Trash', false);
        $response->assertSee('New invoice', false);
        $response->assertSee('No invoices yet. Create one to generate a payment address and share link.', false);
        $response->assertSee('overflow-x-auto', false);
    }

    public function test_invoice_update_redirects_back_to_show_with_status_flash(): void
    {
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
            'description' => 'Initial scope',
            'amount_usd' => 200,
            'btc_rate' => 40_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->put(route('invoices.update', $invoice), [
                'client_id' => $client->id,
                'number' => 'INV-1001',
                'description' => 'Updated scope',
                'amount_usd' => 240,
                'btc_rate' => 40_000,
                'amount_btc' => 0.006,
                'status' => 'sent',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'txid' => null,
            ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('status', 'Invoice updated.');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'description' => 'Updated scope',
            'amount_usd' => '240.00',
            'status' => 'sent',
        ]);
    }

    public function test_invoice_show_displays_edit_and_delete_actions(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-2001',
            'description' => 'Design work',
            'amount_usd' => 150,
            'btc_rate' => 30_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example2',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee(route('invoices.edit', $invoice), false);
        $response->assertSee(route('invoices.destroy', $invoice), false);
        $response->assertSee('Delete', false);
    }

    public function test_invoice_edit_cancel_link_points_to_invoice_show(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001',
            'description' => 'Consulting',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qq0example3',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('href="' . route('invoices.show', $invoice) . '"', false);
    }

    public function test_paid_invoice_cannot_be_reset_to_draft(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4001',
            'description' => 'Completed work',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qq0example4',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.set-status', ['invoice' => $invoice, 'action' => 'draft']));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error', "Paid invoices can't be reset to draft.");
        $response->assertSessionMissing('status');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    public function test_paid_invoice_shows_reset_to_draft_button_as_disabled(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4002',
            'description' => 'Finalized work',
            'amount_usd' => 180,
            'btc_rate' => 45_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0example5',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('data-reset-draft-button="true"', false);
        $response->assertSee('data-reset-draft-disabled="true"', false);
    }

    public function test_paid_invoice_cannot_be_voided(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4003',
            'description' => 'Paid work',
            'amount_usd' => 200,
            'btc_rate' => 50_000,
            'amount_btc' => 0.004,
            'payment_address' => 'tb1qq0example6',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.set-status', ['invoice' => $invoice, 'action' => 'void']));

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('error', "Paid invoices can't be voided.");
        $response->assertSessionMissing('status');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    public function test_paid_invoice_shows_void_button_as_disabled(): void
    {
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4004',
            'description' => 'Settled work',
            'amount_usd' => 210,
            'btc_rate' => 42_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0example7',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('data-void-button="true"', false);
        $response->assertSee('data-void-disabled="true"', false);
    }
}
