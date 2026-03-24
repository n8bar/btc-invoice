<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function test_owner_can_force_delete_trashed_client(): void
    {
        $owner = User::factory()->create();
        $client = $this->makeClient($owner, ['name' => 'Delete Me']);
        $client->delete();

        $response = $this
            ->actingAs($owner)
            ->delete(route('clients.force-destroy', ['clientId' => $client->id]));

        $response->assertRedirect(route('clients.trash'));
        $response->assertSessionHas('status', 'Client permanently deleted.');
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
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

    public function test_owner_can_force_delete_trashed_invoice(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['number' => 'INV-DELETE-ME']);
        $invoice->delete();

        $response = $this
            ->actingAs($owner)
            ->delete(route('invoices.force-destroy', ['invoiceId' => $invoice->id]));

        $response->assertRedirect(route('invoices.trash'));
        $response->assertSessionHas('status', 'Invoice permanently deleted.');
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    public function test_force_delete_is_blocked_for_trashed_invoice_with_ignored_payment_history(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['number' => 'INV-BLOCK-IGNORED']);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-block-ignored',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
            'ignored_at' => now(),
            'ignored_by_user_id' => $owner->id,
            'ignore_reason' => 'Wrong invoice',
        ]);

        $invoice->delete();

        $response = $this
            ->actingAs($owner)
            ->delete(route('invoices.force-destroy', ['invoiceId' => $invoice->id]));

        $response->assertRedirect(route('invoices.trash'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'txid' => 'tx-block-ignored']);

        $this->actingAs($owner)
            ->get(route('invoices.trash'))
            ->assertOk()
            ->assertSee('Permanent delete is currently blocked.', false)
            ->assertSee('ignored payment row', false);
    }

    public function test_force_delete_is_blocked_for_trashed_invoice_with_incoming_reattribution(): void
    {
        $owner = User::factory()->create();
        $sourceInvoice = $this->makeInvoice($owner, ['number' => 'INV-BLOCK-SRC']);
        $destinationInvoice = $this->makeInvoice($owner, ['number' => 'INV-BLOCK-DEST']);

        InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'accounting_invoice_id' => $destinationInvoice->id,
            'txid' => 'tx-block-incoming',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
            'reattributed_at' => now(),
            'reattributed_by_user_id' => $owner->id,
            'reattribute_reason' => 'Belonged elsewhere',
        ]);

        $destinationInvoice->delete();

        $response = $this
            ->actingAs($owner)
            ->delete(route('invoices.force-destroy', ['invoiceId' => $destinationInvoice->id]));

        $response->assertRedirect(route('invoices.trash'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('invoices', ['id' => $destinationInvoice->id]);

        $this->actingAs($owner)
            ->get(route('invoices.trash'))
            ->assertOk()
            ->assertSee('incoming reattribution', false)
            ->assertSee($sourceInvoice->number, false);
    }

    public function test_force_delete_backstop_rejects_direct_delete_when_source_payment_rows_still_exist(): void
    {
        $owner = User::factory()->create();
        $invoice = $this->makeInvoice($owner, ['number' => 'INV-BLOCK-BACKSTOP']);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-block-backstop',
            'sats_received' => 20_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 10.00,
        ]);

        $invoice->delete();

        try {
            Invoice::withTrashed()->findOrFail($invoice->id)->forceDelete();
            $this->fail('Expected the foreign-key backstop to block the force delete.');
        } catch (QueryException $exception) {
            $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        }
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
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0zz',
            'status' => 'draft',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create(array_merge($defaults, $overrides));

        return $invoice->refresh();
    }
}
