<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_grant_and_revoke_support_access(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('settings.support-access.grant'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'support-access-granted');

        $owner->refresh();

        $this->assertNotNull($owner->support_access_granted_at);
        $this->assertNotNull($owner->support_access_expires_at);
        $this->assertSame('v1', $owner->support_access_terms_version);
        $this->assertTrue($owner->hasActiveSupportAccessGrant());

        $this->actingAs($owner)
            ->delete(route('settings.support-access.revoke'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'support-access-revoked');

        $owner->refresh();

        $this->assertNull($owner->support_access_granted_at);
        $this->assertNull($owner->support_access_expires_at);
        $this->assertNull($owner->support_access_terms_version);
        $this->assertFalse($owner->hasActiveSupportAccessGrant());
    }

    public function test_profile_page_shows_support_access_copy_for_owner_accounts(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Tech Support Access')
            ->assertSee('Grant CryptoZing tech support temporary read-only access to your invoices and clients for troubleshooting.')
            ->assertSee('Grant Temporary Support Access');
    }

    public function test_support_dashboard_lists_only_active_owner_grants(): void
    {
        config()->set('support.agent_emails', ['support@example.com']);

        $support = User::factory()->create([
            'email' => 'support@example.com',
        ]);

        $activeOwner = User::factory()->create([
            'name' => 'Active Owner',
        ]);
        $activeOwner->grantSupportAccess(now());

        $expiredOwner = User::factory()->create([
            'name' => 'Expired Owner',
            'support_access_granted_at' => now()->subDays(4),
            'support_access_expires_at' => now()->subDay(),
            'support_access_terms_version' => 'v1',
        ]);

        $this->actingAs($support)
            ->get(route('support.dashboard'))
            ->assertOk()
            ->assertSee('Support Dashboard')
            ->assertSee('Active Owner')
            ->assertDontSee('Expired Owner');
    }

    public function test_support_can_view_granted_owner_invoices_and_clients_in_read_only_surfaces(): void
    {
        config()->set('support.agent_emails', ['support@example.com']);

        $support = User::factory()->create([
            'email' => 'support@example.com',
        ]);

        $owner = User::factory()->create([
            'name' => 'Owner Alpha',
        ]);
        $owner->grantSupportAccess(now());

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Alpha',
            'email' => 'client@example.com',
            'notes' => 'Needs wire fallback.',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-2001',
            'amount_usd' => 125.00,
            'btc_rate' => 50000.00,
            'amount_btc' => 0.00250000,
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'payment_address' => 'tb1qsupportinvoicealpha',
        ]);

        $this->actingAs($support)
            ->get(route('support.owners.invoices.index', $owner))
            ->assertOk()
            ->assertSee('Support Invoice View')
            ->assertSee('Owner Alpha')
            ->assertSee('INV-2001')
            ->assertSee('read-only support access');

        $this->actingAs($support)
            ->get(route('support.owners.invoices.show', [$owner, $invoice]))
            ->assertOk()
            ->assertSee('Support Invoice Detail')
            ->assertSee('tb1qsupportinvoicealpha')
            ->assertSee('Support is viewing this invoice in read-only mode.');

        $this->actingAs($support)
            ->get(route('support.owners.clients.index', $owner))
            ->assertOk()
            ->assertSee('Support Client View')
            ->assertSee('Client Alpha');

        $this->actingAs($support)
            ->get(route('support.owners.clients.show', [$owner, $client]))
            ->assertOk()
            ->assertSee('Support Client Detail')
            ->assertSee('Needs wire fallback.');
    }

    public function test_support_routes_require_an_active_owner_grant(): void
    {
        config()->set('support.agent_emails', ['support@example.com']);

        $support = User::factory()->create([
            'email' => 'support@example.com',
        ]);

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Gamma',
            'email' => 'client-gamma@example.com',
        ]);
        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-3001',
            'amount_usd' => 50.00,
            'btc_rate' => 50000.00,
            'amount_btc' => 0.00100000,
            'status' => 'draft',
            'payment_address' => 'tb1qsupportinvoicebeta',
        ]);

        $this->actingAs($support)
            ->get(route('support.owners.invoices.index', $owner))
            ->assertForbidden();

        $owner->grantSupportAccess(now()->subDays(4));
        $owner->forceFill([
            'support_access_expires_at' => now()->subHour(),
        ])->save();

        $this->actingAs($support)
            ->get(route('support.owners.invoices.show', [$owner, $invoice]))
            ->assertForbidden();
    }

    public function test_support_cannot_write_owner_invoice_or_client_routes_even_with_grant(): void
    {
        config()->set('support.agent_emails', ['support@example.com']);

        $support = User::factory()->create([
            'email' => 'support@example.com',
        ]);

        $owner = User::factory()->create();
        $owner->grantSupportAccess(now());

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Client Beta',
            'email' => 'client-beta@example.com',
        ]);

        $invoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-4001',
            'amount_usd' => 75.00,
            'btc_rate' => 50000.00,
            'amount_btc' => 0.00150000,
            'status' => 'draft',
            'payment_address' => 'tb1qsupportinvoicegamma',
        ]);

        $this->actingAs($support)
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();

        $this->actingAs($support)
            ->delete(route('invoices.destroy', $invoice))
            ->assertForbidden();
    }
}
