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

    public function test_profile_invoice_id_preference_controls_invoice_index_id_column_visibility(): void
    {
        $owner = User::factory()->create(['show_invoice_ids' => false]);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-ID-COL',
            'description' => 'ID column visibility test',
            'amount_usd' => 150,
            'btc_rate' => 50_000,
            'amount_btc' => 0.003,
            'payment_address' => 'tb1qq0exampleidcol',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $this
            ->actingAs($owner)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertDontSee('md:table-cell">ID</th>', false);

        $this
            ->actingAs($owner)
            ->patch(route('profile.update'), [
                'name' => $owner->name,
                'email' => $owner->email,
                'show_invoice_ids' => true,
            ])
            ->assertRedirect('/profile');

        $this
            ->actingAs($owner->fresh())
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('md:table-cell">ID</th>', false)
            ->assertSee('md:table-cell">' . $invoice->id . '</td>', false);
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
        $response->assertSee('sticky top-16', false);
        $response->assertSeeInOrder(['Need to update invoice details?', 'Summary'], false);
        $response->assertSee('>edit<', false);
        $response->assertSee(route('invoices.edit', $invoice), false);
        $response->assertSee(route('invoices.destroy', $invoice), false);
        $response->assertSee('Delete', false);
    }

    public function test_invoice_show_displays_getting_started_progress_strip_when_context_flag_present(): void
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
            'number' => 'INV-GS-2001',
            'description' => 'Design work',
            'amount_usd' => 150,
            'btc_rate' => 30_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0examplegs2',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', ['invoice' => $invoice, 'getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('Back to create invoice', false);
        $response->assertSee(route('getting-started.step', ['step' => 'invoice']), false);
        $response->assertSee('data-getting-started-highlight="deliver-send-invoice"', false);
        $response->assertSee('data-getting-started-highlight="deliver-enable-public-link"', false);
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
        $response->assertSee('Reset to my custom defaults', false);
    }

    public function test_invoice_edit_disables_save_when_public_link_is_enabled(): void
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
            'number' => 'INV-3002',
            'description' => 'Public link lock',
            'amount_usd' => 120,
            'btc_rate' => 50_000,
            'amount_btc' => 0.0024,
            'payment_address' => 'tb1qq0example3002',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);
        $invoice->forceFill([
            'public_enabled' => true,
            'public_token' => 'tok-edit-lock',
            'public_expires_at' => now()->addDay(),
        ])->save();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.edit', $invoice));

        $response->assertOk();
        $response->assertSee('data-edit-save-button="true"', false);
        $response->assertSee('data-edit-save-disabled', false);
        $response->assertSee('Disable public link above to enable saving.', false);
    }

    public function test_invoice_update_can_clear_branding_overrides_to_use_profile_defaults(): void
    {
        $owner = User::factory()->create([
            'branding_heading' => 'Default Invoice Heading',
            'billing_name' => 'Default Biller LLC',
            'invoice_footer_note' => 'Default footer note.',
        ]);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-RESET-DEFAULTS',
            'description' => 'Override reset behavior',
            'amount_usd' => 210,
            'btc_rate' => 42_000,
            'amount_btc' => 0.005,
            'payment_address' => 'tb1qq0exampleresetdefaults',
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'branding_heading_override' => 'Custom Heading',
            'billing_name_override' => 'Custom Biller',
            'invoice_footer_note_override' => 'Custom footer note.',
        ]);

        $response = $this
            ->actingAs($owner)
            ->put(route('invoices.update', $invoice), [
                'client_id' => $client->id,
                'number' => 'INV-RESET-DEFAULTS',
                'description' => 'Override reset behavior',
                'amount_usd' => 210,
                'btc_rate' => 42_000,
                'amount_btc' => 0.005,
                'status' => 'draft',
                'invoice_date' => now()->toDateString(),
                'txid' => null,
                'branding_heading_override' => '',
                'billing_name_override' => '',
                'invoice_footer_note_override' => '',
            ]);

        $response->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertNull($invoice->branding_heading_override);
        $this->assertNull($invoice->billing_name_override);
        $this->assertNull($invoice->invoice_footer_note_override);

        $show = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $show->assertOk();
        $show->assertSee('Default Invoice Heading', false);
        $show->assertSee('Default Biller LLC', false);
        $show->assertSee('Default footer note.', false);
        $show->assertDontSee('Custom Heading', false);
        $show->assertDontSee('Custom Biller', false);
        $show->assertDontSee('Custom footer note.', false);
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

    public function test_expired_public_link_shows_expired_label_and_reactivation_steps(): void
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
            'number' => 'INV-5001',
            'description' => 'Expired link check',
            'amount_usd' => 175,
            'btc_rate' => 45_000,
            'amount_btc' => 0.00388889,
            'payment_address' => 'tb1qq0example8',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        $invoice->forceFill([
            'public_enabled' => true,
            'public_token' => 'tok-expired-copy-check',
            'public_expires_at' => now()->subDay(),
        ])->save();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.show', $invoice));

        $response->assertOk();
        $response->assertSee('data-public-link-expired="true"', false);
        $response->assertSee('Expired', false);
        $response->assertSee('data-public-link-reactivation-help="true"', false);
        $response->assertSee(
            'To unexpire the public link, first disable it, set the expiry options, and enable the public link again.',
            false
        );
    }
}
