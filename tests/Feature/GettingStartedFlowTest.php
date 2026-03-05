<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use App\Models\User;
use App\Models\WalletSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GettingStartedFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_resolves_to_wallet_step_for_new_user(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'wallet']));
    }

    public function test_welcome_redirects_to_start_when_user_already_has_progress(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);

        $this->actingAs($owner)
            ->get(route('getting-started.welcome'))
            ->assertRedirect(route('getting-started.start'));
    }

    public function test_cannot_skip_ahead_of_earliest_incomplete_step(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('getting-started.step', ['step' => 'deliver']))
            ->assertRedirect(route('getting-started.step', ['step' => 'wallet']));
    }

    public function test_start_resolves_to_invoice_step_when_wallet_is_connected(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));
    }

    public function test_start_resolves_to_deliver_step_with_latest_invoice_context(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);

        $client = $this->createClient($owner);
        $olderInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-1001', 'status' => 'draft']);
        $latestInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-1002', 'status' => 'draft']);

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', [
                'step' => 'deliver',
                'invoice' => $latestInvoice->id,
            ]));

        $this->assertNotSame($olderInvoice->id, $latestInvoice->id);
    }

    public function test_deliver_step_prefers_valid_owned_invoice_query_and_ignores_foreign_or_invalid_ids(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->createWallet($owner);

        $client = $this->createClient($owner);
        $fallbackInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-2001', 'status' => 'draft']);
        $preferredInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-2002', 'status' => 'draft']);
        $sentInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-2003', 'status' => 'sent']);
        $voidInvoice = $this->createInvoice($owner, $client, ['number' => 'INV-2004', 'status' => 'void']);

        $otherClient = $this->createClient($otherUser);
        $foreignInvoice = $this->createInvoice($otherUser, $otherClient, ['number' => 'INV-9001', 'status' => 'draft']);

        $response = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $preferredInvoice->id,
        ]));
        $response->assertOk();
        $response->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $foreignResponse = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $foreignInvoice->id,
        ]));
        $foreignResponse->assertOk();
        $foreignResponse->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $sentResponse = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $sentInvoice->id,
        ]));
        $sentResponse->assertOk();
        $sentResponse->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $voidResponse = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $voidInvoice->id,
        ]));
        $voidResponse->assertOk();
        $voidResponse->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $trashed = $this->createInvoice($owner, $client, ['number' => 'INV-2005', 'status' => 'draft']);
        $trashed->delete();

        $trashedResponse = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $trashed->id,
        ]));
        $trashedResponse->assertOk();
        $trashedResponse->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $invalidResponse = $this->actingAs($owner)->get('/getting-started/deliver?invoice=not-an-id');
        $invalidResponse->assertOk();
        $invalidResponse->assertSee(route('invoices.show', [
            'invoice' => $preferredInvoice,
            'getting_started' => 1,
        ]), false);

        $this->assertNotSame($fallbackInvoice->id, $preferredInvoice->id);
    }

    public function test_deliver_step_shows_change_controls_with_draft_invoice_options(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);

        $client = $this->createClient($owner);
        $olderDraft = $this->createInvoice($owner, $client, ['number' => 'INV-3101', 'status' => 'draft']);
        $latestDraft = $this->createInvoice($owner, $client, ['number' => 'INV-3102', 'status' => 'draft']);
        $this->createInvoice($owner, $client, ['number' => 'INV-3103', 'status' => 'sent']);
        $this->createInvoice($owner, $client, ['number' => 'INV-3104', 'status' => 'void']);

        $response = $this->actingAs($owner)->get(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $latestDraft->id,
        ]));

        $response->assertOk();
        $response->assertSee('Target invoice', false);
        $response->assertSee('Change', false);
        $response->assertSee('Create new invoice instead', false);
        $response->assertSee('INV-3101', false);
        $response->assertSee('INV-3102', false);
        $response->assertDontSee('INV-3103', false);
        $response->assertDontSee('INV-3104', false);

        $this->assertNotSame($olderDraft->id, $latestDraft->id);
    }

    public function test_deliver_step_redirects_to_invoice_step_when_no_invoice_exists(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);

        $this->actingAs($owner)
            ->get(route('getting-started.step', ['step' => 'deliver']))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));
    }

    public function test_replay_requires_new_draft_invoice_before_deliver_step(): void
    {
        $owner = User::factory()->create([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => now()->subMinute(),
            'getting_started_replay_wallet_verified_at' => now(),
        ]);
        $this->createWallet($owner);
        $client = $this->createClient($owner);

        $replayInvoice = $this->createInvoice($owner, $client, ['status' => 'paid']);
        $replayInvoice->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));

        $this->actingAs($owner)
            ->get(route('getting-started.step', ['step' => 'deliver']))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));
    }

    public function test_invoice_step_shows_draft_required_warning_when_non_replay_has_only_non_draft_invoices(): void
    {
        $owner = User::factory()->create([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => null,
            'getting_started_replay_wallet_verified_at' => null,
        ]);
        $this->createWallet($owner);
        $client = $this->createClient($owner);

        $this->createInvoice($owner, $client, ['status' => 'paid']);

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));

        $response = $this->actingAs($owner)->get(route('getting-started.step', ['step' => 'invoice']));
        $response->assertOk();
        $response->assertSee('Your latest invoice is no longer draft. Create a new draft invoice to continue.', false);
        $response->assertSeeInOrder([
            'Your latest invoice is no longer draft. Create a new draft invoice to continue.',
            'Create new draft invoice',
        ], false);
        $response->assertSee('If this happened immediately, your wallet account may already have activity from outside CryptoZing.', false);
        $response->assertSeeInOrder([
            'Success criteria',
            'Create at least one draft invoice.',
            'Create new draft invoice',
        ], false);
        $response->assertSee(route('invoices.create', ['getting_started' => 1]), false);
        $response->assertDontSee('Go to create invoice', false);
        $response->assertDontSee('No new draft invoice is available for delivery.', false);
    }

    public function test_invoice_step_hides_required_step_notice_when_returning_from_deliver_for_new_draft(): void
    {
        $owner = User::factory()->create([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => now()->subMinute(),
            'getting_started_replay_wallet_verified_at' => now(),
        ]);
        $this->createWallet($owner);
        $client = $this->createClient($owner);

        $replayInvoice = $this->createInvoice($owner, $client, ['status' => 'draft']);
        $replayInvoice->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->actingAs($owner)->get(route('getting-started.step', ['step' => 'invoice']));

        $response->assertOk();
        $response->assertDontSee('next required step is step', false);
        $response->assertDontSee('Open required step', false);
    }

    public function test_dismiss_and_reopen_update_getting_started_state(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('getting-started.dismiss'))
            ->assertRedirect(route('dashboard'));

        $owner->refresh();
        $this->assertTrue($owner->getting_started_dismissed);
        $this->assertNotNull($owner->getting_started_completed_at);
        $this->assertNull($owner->getting_started_replay_started_at);
        $this->assertNull($owner->getting_started_replay_wallet_verified_at);

        $this->actingAs($owner)
            ->post(route('getting-started.reopen'))
            ->assertRedirect(route('getting-started.start'));

        $owner->refresh();
        $this->assertFalse($owner->getting_started_dismissed);
        $this->assertNull($owner->getting_started_completed_at);
        $this->assertNull($owner->getting_started_replay_started_at);
        $this->assertNull($owner->getting_started_replay_wallet_verified_at);
    }

    public function test_completed_user_can_replay_with_post_replay_progress_requirements(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);
        $client = $this->createClient($owner);

        $completedInvoice = $this->createInvoice($owner, $client, ['status' => 'draft']);
        $completedInvoice->enablePublicShare();
        $baselineDelivery = InvoiceDelivery::create([
            'invoice_id' => $completedInvoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'cc' => null,
            'message' => 'Completed baseline',
            'dispatched_at' => now()->subMinute(),
        ]);
        $baselineDelivery->forceFill([
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ])->save();

        $owner->forceFill([
            'getting_started_completed_at' => now(),
            'getting_started_dismissed' => false,
        ])->save();

        $this->actingAs($owner)
            ->post(route('getting-started.reopen'))
            ->assertRedirect(route('getting-started.start'));

        $owner->refresh();
        $this->assertNull($owner->getting_started_completed_at);
        $this->assertFalse($owner->getting_started_dismissed);
        $this->assertNotNull($owner->getting_started_replay_started_at);
        $this->assertNull($owner->getting_started_replay_wallet_verified_at);

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'wallet']));

        $owner->forceFill([
            'getting_started_replay_wallet_verified_at' => now()->addSecond(),
        ])->save();

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));

        $this->actingAs($owner)
            ->get(route('getting-started.step', ['step' => 'deliver']))
            ->assertRedirect(route('getting-started.step', ['step' => 'invoice']));

        $replayInvoice = $this->createInvoice($owner, $client, ['status' => 'draft']);
        $replayInvoice->forceFill([
            'created_at' => now()->addSeconds(2),
            'updated_at' => now()->addSeconds(2),
        ])->save();

        $this->actingAs($owner)
            ->get(route('getting-started.start'))
            ->assertRedirect(route('getting-started.step', [
                'step' => 'deliver',
                'invoice' => $replayInvoice->id,
            ]));

        $replayInvoice->enablePublicShare();
        $replayInvoice->refresh();
        $replayInvoice->forceFill([
            'created_at' => now()->addSeconds(3),
            'updated_at' => now()->addSeconds(3),
        ])->save();

        $replayDelivery = InvoiceDelivery::create([
            'invoice_id' => $replayInvoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'cc' => null,
            'message' => 'Replay complete',
            'dispatched_at' => now()->addSeconds(4),
        ]);
        $replayDelivery->forceFill([
            'created_at' => now()->addSeconds(4),
            'updated_at' => now()->addSeconds(4),
        ])->save();

        $response = $this->actingAs($owner)->get(route('getting-started.start'));
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', 'Getting started complete.');

        $owner->refresh();
        $this->assertNotNull($owner->getting_started_completed_at);
        $this->assertNull($owner->getting_started_replay_started_at);
        $this->assertNull($owner->getting_started_replay_wallet_verified_at);
    }

    public function test_start_marks_flow_complete_and_redirects_to_dashboard_when_steps_are_done(): void
    {
        $owner = User::factory()->create();
        $this->createWallet($owner);
        $client = $this->createClient($owner);
        $invoice = $this->createInvoice($owner, $client, ['status' => 'draft']);
        $invoice->enablePublicShare();

        InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $owner->id,
            'type' => 'send',
            'status' => 'queued',
            'recipient' => $client->email,
            'cc' => null,
            'message' => 'Test',
            'dispatched_at' => now(),
        ]);

        $response = $this->actingAs($owner)->get(route('getting-started.start'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', 'Getting started complete.');

        $owner->refresh();
        $this->assertFalse($owner->getting_started_dismissed);
        $this->assertNotNull($owner->getting_started_completed_at);
    }

    public function test_done_users_are_redirected_away_from_getting_started(): void
    {
        $owner = User::factory()->create([
            'getting_started_completed_at' => now(),
            'getting_started_dismissed' => true,
        ]);

        $response = $this->actingAs($owner)->get(route('getting-started.step', ['step' => 'wallet']));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status', 'Getting started hidden.');
    }

    private function createWallet(User $user): WalletSetting
    {
        return WalletSetting::create([
            'user_id' => $user->id,
            'network' => 'testnet',
            'bip84_xpub' => 'vpub' . str_repeat('a', 40),
            'next_derivation_index' => 0,
            'onboarded_at' => now(),
        ]);
    }

    private function createClient(User $user): Client
    {
        return Client::create([
            'user_id' => $user->id,
            'name' => 'Acme Co',
            'email' => 'billing+' . $user->id . '@example.test',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createInvoice(User $user, Client $client, array $overrides = []): Invoice
    {
        $sequence = (int) Invoice::withTrashed()->max('id') + 1;

        return Invoice::create(array_merge([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-' . str_pad((string) (1000 + $sequence), 4, '0', STR_PAD_LEFT),
            'amount_usd' => 100,
            'btc_rate' => 40_000,
            'amount_btc' => 0.0025,
            'payment_address' => 'tb1qexample' . $sequence,
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ], $overrides));
    }
}
