<?php

namespace Tests\Feature;

use App\Mail\NotificationBrandingPreviewMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserWalletAccount;
use App\Models\WalletKeyCursor;
use App\Models\WalletSetting;
use App\Services\BtcRate;
use App\Services\HdWallet;
use App\Services\Blockchain\MempoolClient;
use App\Services\WalletKeyLineage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use PDOException;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const REALISTIC_TESTNET_TPUB = 'tpubDCMX5n5xeyKFQ1R98FTjQ21An9e2SgN8gF5pa4DJNfQd8B5CYCqkkWXEmH4YrxRAEDzFSv25yineuGfvFAg9tWJcGakvm7Ft5e41jQZ2bHk';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('wallet.default_network', 'testnet');
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_count', 0);
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_cap', 0);
    }

    public function test_invoice_store_applies_user_defaults(): void
    {
        $owner = User::factory()->create([
            'invoice_default_description' => 'Weekly retainer',
            'invoice_default_terms_days' => 10,
        ]);
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
            'notes' => null,
        ]);

        $this->mock(\App\Services\HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'number' => 'INV-TEST',
                'description' => '',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
            ])
            ->assertRedirect(route('invoices.index'));

        $invoice = $owner->invoices()->latest('id')->first();
        $this->assertSame('Weekly retainer', $invoice->description);
        $this->assertSame('2025-01-11', Carbon::parse($invoice->due_date)->toDateString());
        $this->assertSame('testnet', $invoice->wallet_network);
        $this->assertSame($this->walletFingerprint('testnet', 'vpub-test-key'), $invoice->wallet_key_fingerprint);
        $this->assertSame(0, (int) $invoice->derivation_index);
        $this->assertSame(1, (int) $this->walletCursorFor($owner, 'vpub-test-key')->next_derivation_index);
    }

    public function test_invoice_store_forces_new_invoices_to_draft(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'number' => 'INV-FORCE-DRAFT',
                'description' => 'Should still save as draft',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'sent',
                'invoice_date' => '2025-01-01',
            ])
            ->assertRedirect(route('invoices.index'));

        $invoice = $owner->invoices()->latest('id')->firstOrFail();
        $this->assertSame('draft', $invoice->status);
    }

    public function test_invoice_store_snapshots_unsupported_wallet_state_when_wallet_is_flagged(): void
    {
        $owner = User::factory()->create();
        $wallet = $this->createWalletSetting($owner);
        $wallet->markUnsupportedConfiguration(
            source: 'proactive',
            reason: 'outside_receive_activity',
            details: 'Detected prior outside receive activity for this account.',
            flaggedAt: now()->subMinute(),
        );

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'number' => 'INV-UNSUPPORTED-SNAPSHOT',
                'description' => 'Flagged wallet snapshot',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'invoice_date' => '2025-01-01',
            ])
            ->assertRedirect(route('invoices.index'));

        $invoice = $owner->invoices()->latest('id')->firstOrFail();

        $this->assertTrue($invoice->unsupported_configuration_flagged);
        $this->assertSame('proactive', $invoice->unsupported_configuration_source);
        $this->assertSame('outside_receive_activity', $invoice->unsupported_configuration_reason);
        $this->assertSame('Detected prior outside receive activity for this account.', $invoice->unsupported_configuration_details);
        $this->assertNotNull($invoice->unsupported_configuration_flagged_at);
    }

    public function test_invoice_create_prefills_defaults(): void
    {
        $owner = User::factory()->create([
            'invoice_default_description' => 'Consulting retainer',
            'invoice_default_terms_days' => 7,
        ]);
        $this->createWalletSetting($owner);
        Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('Consulting retainer', false);
        $expectedDue = now()->addDays(7)->toDateString();
        $response->assertSee('value="' . $expectedDue . '"', false);
    }

    public function test_invoice_create_hides_status_selector(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);
        Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertDontSee('name="status"', false);
        $response->assertSee('Reset to my custom defaults', false);
    }

    public function test_invoice_create_shows_unsupported_warning_and_cta_when_wallet_is_flagged(): void
    {
        $owner = User::factory()->create();
        $wallet = $this->createWalletSetting($owner);
        $wallet->markUnsupportedConfiguration(
            source: 'proactive',
            reason: 'outside_receive_activity',
            details: 'Detected prior outside receive activity for this account.',
            flaggedAt: now()->subMinute(),
        );

        Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('data-unsupported-invoice-create-warning', false);
        $response->assertSee('We found wallet activity outside CryptoZing.');
        $response->assertSee('If you continue now, this invoice will be created as an unsupported invoice.');
        $response->assertSee('Connect a fresh dedicated account key');
        $response->assertSee('Create Unsupported Invoice');
        $response->assertDontSee('>Save<', false);
    }

    public function test_invoice_create_prefill_rate_is_rounded_to_two_decimals(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);
        Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        Cache::put(BtcRate::CACHE_KEY, [
            'rate_usd' => 64_946.955,
            'as_of' => now(),
            'source' => 'coinbase:spot',
        ], BtcRate::TTL);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('value="64946.96"', false);
        $response->assertDontSee('value="64946.955"', false);
    }

    public function test_invoice_create_suggested_number_skips_trashed_invoices(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $trashedInvoice = Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-0001',
            'description' => 'Trashed seed invoice',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qtrashedinvoice0000000000000000000',
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
        ]);
        $trashedInvoice->delete();

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('placeholder="INV-0002"', false);
    }

    public function test_invoice_create_shows_client_gate_when_no_clients_exist(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create', ['getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('Create your first client', false);
        $response->assertSee('Back to connect wallet', false);
        $response->assertSee('name="return_to"', false);
        $response->assertSee(route('invoices.create', ['getting_started' => 1], false), false);
        $response->assertSee('data-getting-started-highlight="invoice-create-client"', false);
        $response->assertDontSee('Client <span class="text-red-600" aria-hidden="true">*</span>', false);
    }

    public function test_invoice_create_shows_save_highlight_when_getting_started_with_clients(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);
        Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create', ['getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('data-getting-started-highlight="invoice-save"', false);
        $response->assertDontSee('Create your first client', false);
    }

    public function test_client_store_from_invoice_gate_redirects_back_to_invoice_create_context(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->post(route('clients.store'), [
                'name' => 'Acme',
                'email' => 'billing@acme.test',
                'return_to' => route('invoices.create', ['getting_started' => 1], false),
            ]);

        $response->assertRedirect(route('invoices.create', ['getting_started' => 1]));
        $response->assertSessionHas('status', 'Client created.');
        $this->assertDatabaseHas('clients', [
            'user_id' => $owner->id,
            'name' => 'Acme',
        ]);
    }

    public function test_client_store_ignores_external_return_to_and_redirects_to_clients_index(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->post(route('clients.store'), [
                'name' => 'Acme',
                'email' => 'billing@acme.test',
                'return_to' => 'https://evil.example/phish',
            ]);

        $response->assertRedirect(route('clients.index'));
    }

    public function test_invoice_store_redirects_to_getting_started_deliver_step_when_context_flag_present(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(\App\Services\HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'number' => 'INV-GS-1',
                'description' => 'First run',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
                'getting_started' => 1,
            ]);

        $invoice = $owner->invoices()->latest('id')->firstOrFail();

        $response->assertRedirect(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $invoice->id,
        ]));
        $response->assertSessionHas('status', 'Invoice created.');
    }

    public function test_invoice_store_derive_failure_preserves_getting_started_context_on_wallet_redirect(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andThrow(new \RuntimeException('derive failed'));
        });

        $response = $this
            ->actingAs($owner)
            ->from(route('invoices.create', ['getting_started' => 1]))
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'number' => 'INV-GS-FAIL-1',
                'description' => 'First run',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
                'getting_started' => 1,
            ]);

        $response->assertRedirect(route('wallet.settings.edit', ['getting_started' => 1]));
        $response->assertSessionHasErrors('bip84_xpub');
    }

    public function test_invoice_store_global_number_collision_during_onboarding_redirects_to_deliver_after_db_fix(): void
    {
        $existingOwner = User::factory()->create();
        $existingClient = Client::create([
            'user_id' => $existingOwner->id,
            'name' => 'Existing Client',
            'email' => 'existing@acme.test',
        ]);

        Invoice::create([
            'user_id' => $existingOwner->id,
            'client_id' => $existingClient->id,
            'number' => 'INV-0001',
            'description' => 'Existing invoice',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qexistinginvoice0000000000000000000',
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
        ]);

        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $response = $this
            ->actingAs($owner)
            ->from(route('invoices.create', ['getting_started' => 1]))
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'description' => 'First run',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
                'getting_started' => 1,
            ]);

        $newInvoice = $owner->invoices()->latest('id')->firstOrFail();

        $response->assertRedirect(route('getting-started.step', [
            'step' => 'deliver',
            'invoice' => $newInvoice->id,
        ]));
        $response->assertSessionHas('status', 'Invoice created.');
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('invoices', [
            'user_id' => $owner->id,
            'number' => 'INV-0001',
        ]);
        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_invoice_store_allows_same_invoice_number_for_different_users(): void
    {
        $existingOwner = User::factory()->create();
        $existingClient = Client::create([
            'user_id' => $existingOwner->id,
            'name' => 'Existing Client',
            'email' => 'existing@acme.test',
        ]);

        Invoice::create([
            'user_id' => $existingOwner->id,
            'client_id' => $existingClient->id,
            'number' => 'INV-0001',
            'description' => 'Existing invoice',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qexistinginvoice0000000000000000000',
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
        ]);

        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'number' => 'INV-0001',
                'client_id' => $client->id,
                'description' => 'Second user invoice',
                'amount_usd' => 100,
                'btc_rate' => 50_000,
                'amount_btc' => 0.002,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
            ]);

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHas('status', 'Invoice created.');

        $this->assertDatabaseHas('invoices', [
            'user_id' => $owner->id,
            'number' => 'INV-0001',
        ]);
    }

    public function test_invoice_store_retries_with_new_number_when_insert_collision_occurs_after_validation(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $lineage = [
            'payment_address' => 'tb1qretryaddress00000000000000000000000',
            'derivation_index' => 0,
            'wallet_key_fingerprint' => app(WalletKeyLineage::class)->fingerprint('testnet', 'vpub-test-key'),
            'wallet_network' => 'testnet',
        ];
        $collision = $this->invoiceNumberCollisionException();

        $this->mock(WalletKeyLineage::class, function ($mock) use ($owner, $client, $lineage, $collision): void {
            $mock->shouldReceive('previewNextAssignment')
                ->once()
                ->andReturn($lineage);
            $mock->shouldReceive('withPreparedAssignment')
                ->once()
                ->andReturnUsing(function ($wallet, $prepared, $callback) use ($owner, $client, $collision): never {
                    Invoice::create([
                        'user_id' => $owner->id,
                        'client_id' => $client->id,
                        'number' => 'INV-0042',
                        'description' => 'Inserted during request to simulate race',
                        'amount_usd' => 95,
                        'btc_rate' => 50_000,
                        'amount_btc' => 0.0019,
                        'payment_address' => 'tb1qexistingduringrequest000000000000000',
                        'derivation_index' => 9999,
                        'status' => 'draft',
                        'invoice_date' => '2025-01-01',
                    ]);

                    throw $collision;
                });
            $mock->shouldReceive('withPreparedAssignment')
                ->once()
                ->andReturnUsing(function ($wallet, $prepared, $callback) use ($owner, $lineage) {
                    $result = $callback($lineage);

                    $owner->walletKeyCursors()->create([
                        'network' => 'testnet',
                        'key_fingerprint' => $lineage['wallet_key_fingerprint'],
                        'next_derivation_index' => 1,
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                    ]);

                    return $result;
                });
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('invoices.store'), [
                'number' => 'INV-0042',
                'client_id' => $client->id,
                'description' => 'Invoice that should retry',
                'amount_usd' => 120,
                'btc_rate' => 50_000,
                'amount_btc' => 0.0024,
                'status' => 'draft',
                'invoice_date' => '2025-01-01',
            ]);

        $response->assertRedirect(route('invoices.index'));
        $response->assertSessionHas('status', 'Invoice created. Number adjusted to INV-0043 due to a collision.');
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('invoices', [
            'user_id' => $owner->id,
            'number' => 'INV-0043',
        ]);
        $this->assertSame(2, $owner->invoices()->count());

        $cursor = $this->walletCursorFor($owner, 'vpub-test-key');
        $this->assertSame(1, (int) $cursor->next_derivation_index);
    }

    public function test_user_can_add_and_remove_additional_wallet_accounts(): void
    {
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.accounts.store'), [
                'label' => 'Cold storage',
                'bip84_xpub' => 'vpub' . str_repeat('a', 20),
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $this->assertDatabaseHas('user_wallet_accounts', [
            'user_id' => $owner->id,
            'label' => 'Cold storage',
        ]);

        $account = UserWalletAccount::where('user_id', $owner->id)->first();

        $this
            ->actingAs($owner)
            ->delete(route('wallet.settings.accounts.destroy', $account))
            ->assertRedirect(route('wallet.settings.edit'));

        $this->assertDatabaseMissing('user_wallet_accounts', [
            'id' => $account->id,
        ]);
    }

    public function test_user_can_update_invoice_settings_page(): void
    {
        $owner = User::factory()->create([
            'show_overpayment_gratuity_note' => true,
            'show_qr_refresh_reminder' => true,
        ]);

        $this
            ->actingAs($owner)
            ->patch(route('settings.invoice.update'), [
                'branding_heading' => 'CryptoZing Invoice',
                'billing_name' => 'CryptoZing LLC',
                'billing_email' => 'billing@cryptozing.app',
                'invoice_default_description' => 'Weekly retainer',
                'invoice_default_terms_days' => 14,
                'show_overpayment_gratuity_note' => false,
                'show_qr_refresh_reminder' => false,
            ])
            ->assertRedirect(route('settings.invoice.edit'));

        $owner->refresh();
        $this->assertSame('Weekly retainer', $owner->invoice_default_description);
        $this->assertSame(14, $owner->invoice_default_terms_days);
        $this->assertSame('CryptoZing Invoice', $owner->branding_heading);
        $this->assertFalse($owner->show_overpayment_gratuity_note);
        $this->assertFalse($owner->show_qr_refresh_reminder);
    }

    public function test_invoice_settings_page_shows_client_facing_payment_note_toggles(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('settings.invoice.edit'));

        $response->assertOk();
        $response->assertSee('Client-facing payment notes', false);
        $response->assertSee('name="show_overpayment_gratuity_note"', false);
        $response->assertSee('name="show_qr_refresh_reminder"', false);
    }

    public function test_user_can_update_notification_mail_branding_settings(): void
    {
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->patch(route('settings.notifications.update'), [
                'mail_brand_name' => 'Phase 3 Mail',
                'mail_brand_tagline' => 'Client receipts reviewed by humans',
                'mail_footer_blurb' => 'Phase 3 footer blurb.',
                'show_mail_logo' => false,
            ])
            ->assertRedirect(route('settings.notifications.edit'));

        $owner->refresh();
        $this->assertSame('Phase 3 Mail', $owner->mail_brand_name);
        $this->assertSame('Client receipts reviewed by humans', $owner->mail_brand_tagline);
        $this->assertSame('Phase 3 footer blurb.', $owner->mail_footer_blurb);
        $this->assertFalse($owner->show_mail_logo);

        $this
            ->actingAs($owner)
            ->patch(route('settings.notifications.update'), [
                'mail_brand_name' => '',
                'mail_brand_tagline' => '',
                'mail_footer_blurb' => '',
                'show_mail_logo' => true,
            ])
            ->assertRedirect(route('settings.notifications.edit'));

        $owner->refresh();
        $this->assertNull($owner->mail_brand_name);
        $this->assertNull($owner->mail_brand_tagline);
        $this->assertNull($owner->mail_footer_blurb);
        $this->assertTrue($owner->show_mail_logo);
    }

    public function test_notification_settings_page_explains_manual_receipt_review_model_and_mail_branding_scope(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('settings.notifications.edit'));

        $response->assertOk();
        $response->assertSeeText('Payment emails');
        $response->assertSeeText('Detected payments can send a narrow acknowledgment right away when the app can safely say only that a payment was detected.');
        $response->assertSeeText('Client receipts are always reviewed before sending from the paid invoice page.');
        $response->assertSeeText('Mail branding');
        $response->assertSeeText('These fields only change the shared mail shell for active notification emails.');
        $response->assertSee('name="mail_brand_name"', false);
        $response->assertSee('name="mail_brand_tagline"', false);
        $response->assertSee('name="mail_footer_blurb"', false);
        $response->assertSee('name="show_mail_logo"', false);
        $response->assertSeeText('Send yourself a test email');
        $response->assertSee(route('settings.notifications.preview'), false);
        $response->assertDontSee('name="auto_receipt_emails"', false);
        $response->assertDontSeeText('dashboard, invoices list, and invoice payment history will point you to the review/send action');
        $response->assertDontSeeText('RC');
        $response->assertDontSeeText('MS16');
        $response->assertSee('Save settings');
    }

    public function test_owner_can_send_branded_notification_test_email(): void
    {
        Mail::fake();

        $owner = User::factory()->create([
            'mail_brand_name' => 'Phase 3 Mail',
            'mail_brand_tagline' => 'Owner-reviewed bitcoin receipts',
            'mail_footer_blurb' => 'Phase 3 custom footer blurb.',
            'show_mail_logo' => false,
        ]);

        $this->actingAs($owner)
            ->post(route('settings.notifications.preview'))
            ->assertRedirect(route('settings.notifications.edit'))
            ->assertSessionHas('status', 'notification-preview-sent')
            ->assertSessionHas('preview_email', $owner->email);

        Mail::assertSent(NotificationBrandingPreviewMail::class, function (NotificationBrandingPreviewMail $mail) use ($owner) {
            return $mail->hasTo($owner->email)
                && $mail->user->is($owner);
        });

        $this->assertDatabaseCount('invoice_deliveries', 0);
    }

    public function test_notification_test_email_is_cooldown_protected(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        $key = 'notification-branding-preview:' . $owner->getKey();

        $this->actingAs($owner)
            ->post(route('settings.notifications.preview'))
            ->assertRedirect(route('settings.notifications.edit'));

        $this->actingAs($owner)
            ->post(route('settings.notifications.preview'))
            ->assertRedirect(route('settings.notifications.edit'))
            ->assertSessionHas('status', 'notification-preview-throttled');

        Mail::assertSent(NotificationBrandingPreviewMail::class, 1);

        RateLimiter::clear($key);
    }

    public function test_settings_tabs_render_on_all_settings_pages(): void
    {
        $owner = User::factory()->create();

        $routes = [
            [
                'url' => route('profile.edit'),
                'active' => route('profile.edit'),
            ],
            [
                'url' => route('wallet.settings.edit'),
                'active' => route('wallet.settings.edit'),
            ],
            [
                'url' => route('settings.invoice.edit'),
                'active' => route('settings.invoice.edit'),
            ],
            [
                'url' => route('settings.notifications.edit'),
                'active' => route('settings.notifications.edit'),
            ],
        ];

        foreach ($routes as $tab) {
            $response = $this->actingAs($owner)->get($tab['url']);
            $response->assertOk();
            $response->assertSee('Account', false);
            $response->assertSee(route('profile.edit'), false);
            $response->assertSee(route('wallet.settings.edit'), false);
            $response->assertSee(route('settings.invoice.edit'), false);
            $response->assertSee(route('settings.notifications.edit'), false);
            $response->assertSee('h-screen overflow-hidden', false);
            $response->assertSee('min-h-0 flex-1 overflow-y-auto', false);
            $response->assertDontSee('sticky top-16', false);
            $response->assertDontSee('sticky top-32', false);

            $content = $response->getContent();
            $this->assertIsString($content);
            $this->assertMatchesRegularExpression(
                '/<a href="' . preg_quote($tab['active'], '/') . '"[^>]*class="[^"]*border-indigo-400[^"]*"/',
                $content
            );
        }
    }

    public function test_show_invoice_ids_toggle_is_only_available_on_account_tab(): void
    {
        $owner = User::factory()->create();

        $account = $this->actingAs($owner)->get(route('profile.edit'));
        $account->assertOk();
        $account->assertSee('name="show_invoice_ids"', false);

        $wallet = $this->actingAs($owner)->get(route('wallet.settings.edit'));
        $wallet->assertOk();
        $wallet->assertDontSee('name="show_invoice_ids"', false);

        $invoice = $this->actingAs($owner)->get(route('settings.invoice.edit'));
        $invoice->assertOk();
        $invoice->assertDontSee('name="show_invoice_ids"', false);

        $notifications = $this->actingAs($owner)->get(route('settings.notifications.edit'));
        $notifications->assertOk();
        $notifications->assertDontSee('name="show_invoice_ids"', false);
    }

    public function test_user_cannot_delete_other_wallet_account(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $account = UserWalletAccount::factory()->create([
            'user_id' => $other->id,
        ]);

        $this
            ->actingAs($owner)
            ->delete(route('wallet.settings.accounts.destroy', $account))
            ->assertForbidden();
    }

    public function test_wallet_settings_shows_testnet_helper_when_not_mainnet(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee('Testnet (for testing only). Real payments require mainnet.', false);
    }

    public function test_wallet_settings_hides_network_helper_on_mainnet(): void
    {
        Config::set('wallet.default_network', 'mainnet');
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertDontSee('Testnet (for testing only). Real payments require mainnet.', false);
    }

    public function test_wallet_settings_prefills_existing_wallet_key(): void
    {
        $owner = User::factory()->create();
        $storedKey = 'vpub' . str_repeat('a', 20);
        WalletSetting::create([
            'user_id' => $owner->id,
            'network' => 'testnet',
            'bip84_xpub' => $storedKey,
            'onboarded_at' => now(),
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*id="bip84_xpub"[^>]*rows="3"[^>]*>\s*' . preg_quote($storedKey, '/') . '\s*<\/textarea>/s',
            $content
        );
    }

    public function test_wallet_settings_explains_dedicated_receiving_account_requirement(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee('Use a dedicated receiving account key here.', false);
        $response->assertSee('CryptoZing expects a dedicated account key for invoice receives.', false);
        $response->assertSee('If the same account receives payments elsewhere, CryptoZing can attach a payment to the wrong invoice.', false);
        $response->assertSee('You’ll still view balances and spend from this account in your wallet app. CryptoZing does not show balances or send bitcoin.', false);
        $response->assertSee('Usually starts with', false);
        $response->assertSee('vpub or tpub', false);
    }

    public function test_wallet_settings_mainnet_surface_omits_testnet_prefixes(): void
    {
        Config::set('wallet.default_network', 'mainnet');
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertDontSee('vpub', false);
        $response->assertDontSee('tpub', false);
        $response->assertSee('Usually starts with', false);
        $response->assertSee('xpub or zpub', false);
    }

    public function test_wallet_settings_links_to_dedicated_receiving_account_helpful_notes(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee(
            'href="' . route('help', ['from' => 'wallet-settings']) . '#dedicated-receiving-account"',
            false
        );
        $response->assertSee('Read why CryptoZing needs a dedicated receiving account.', false);
    }

    public function test_wallet_settings_show_unsupported_warning_and_navigation_indicators_when_wallet_is_flagged(): void
    {
        $owner = User::factory()->create();
        $wallet = $this->createWalletSetting($owner);
        $wallet->markUnsupportedConfiguration(
            source: 'proactive',
            reason: 'outside_receive_activity',
            details: 'Detected prior outside receive activity for this account.',
            flaggedAt: now()->subMinute(),
        );

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee('data-user-menu-unsupported-label', false);
        $response->assertSee('data-settings-alert-dot', false);
        $response->assertSee('data-wallet-tab-alert-dot', false);
        $response->assertSee('data-wallet-unsupported-warning', false);
        $response->assertSee('We found wallet activity outside CryptoZing.', false);
        $response->assertSee('Automatic payment tracking is no longer reliable for this wallet account.', false);
        $response->assertSee('Connect a fresh dedicated account key to keep future invoices on a dedicated receive path.', false);
    }

    public function test_wallet_settings_hide_unsupported_indicators_when_wallet_is_not_flagged(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertDontSee('data-user-menu-unsupported-label', false);
        $response->assertDontSee('data-settings-alert-dot', false);
        $response->assertDontSee('data-wallet-tab-alert-dot', false);
        $response->assertDontSee('data-wallet-unsupported-warning', false);
        $response->assertDontSee('We found wallet activity outside CryptoZing.', false);
    }

    public function test_wallet_settings_shows_getting_started_progress_strip_when_context_flag_present(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit', ['getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('Back to welcome', false);
        $response->assertSee(route('getting-started.welcome'), false);
        $response->assertSee('Recommended for setup', false);
        $response->assertSee('data-getting-started-highlight="wallet-key-input"', false);
        $response->assertSee('data-getting-started-highlight="wallet-save"', false);
        $response->assertSee('data-getting-started-highlight="wallet-key-helper"', false);
    }

    public function test_wallet_settings_replay_mode_shows_verify_label_and_cancel_toggle_wiring(): void
    {
        $owner = User::factory()->create([
            'getting_started_completed_at' => null,
            'getting_started_dismissed' => false,
            'getting_started_replay_started_at' => now()->subMinute(),
            'getting_started_replay_wallet_verified_at' => null,
        ]);
        $this->createWalletSetting($owner);

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit', ['getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('Review it, then click Verify wallet to confirm this step.', false);
        $response->assertSee('hasValueChanged() ? \'👉 Save wallet\' : \'👉 Verify wallet\'', false);
        $response->assertSee('data-wallet-replay-cancel', false);
        $response->assertSee('style="display: none;"', false);
    }

    public function test_wallet_settings_accepts_realistic_testnet_wallet_key_length(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $wallet = WalletSetting::where('user_id', $owner->id)->firstOrFail();
        $raw = DB::table('wallet_settings')->where('id', $wallet->id)->value('bip84_xpub');

        $this->assertSame(self::REALISTIC_TESTNET_TPUB, $wallet->bip84_xpub);
        $this->assertNotSame(self::REALISTIC_TESTNET_TPUB, $raw);
        $this->assertGreaterThan(255, strlen($raw));
    }

    public function test_wallet_settings_update_redirects_to_getting_started_when_context_flag_present(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
                'getting_started' => 1,
            ]);

        $response->assertRedirect(route('getting-started.start'));
        $response->assertSessionHas('status', 'Wallet settings saved.');
    }

    public function test_wallet_settings_update_logs_dedicated_guidance_save_context(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();
        Log::spy();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
                'getting_started' => 1,
            ])
            ->assertRedirect(route('getting-started.start'));

        $wallet = WalletSetting::where('user_id', $owner->id)->firstOrFail();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('wallet.settings.saved_with_dedicated_guidance', Mockery::on(
                function (array $context) use ($owner, $wallet): bool {
                    return $context['user_id'] === $owner->id
                        && $context['wallet_setting_id'] === $wallet->id
                        && $context['network'] === 'testnet'
                        && $context['surface'] === 'getting_started'
                        && $context['getting_started'] === true
                        && $context['wallet_key_replaced'] === false
                        && $context['unsupported_configuration_previously_active'] === false
                        && $context['unsupported_configuration_active'] === false
                        && $context['unsupported_configuration_source'] === null
                        && ! array_key_exists('bip84_xpub', $context);
                }
            ));
    }

    public function test_wallet_settings_update_resumes_cursor_when_same_key_is_resaved(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();
        WalletSetting::create([
            'user_id' => $owner->id,
            'network' => 'testnet',
            'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            'onboarded_at' => now()->subDay(),
        ]);
        $owner->walletKeyCursors()->create([
            'network' => 'testnet',
            'key_fingerprint' => app(WalletKeyLineage::class)->fingerprint('testnet', self::REALISTIC_TESTNET_TPUB),
            'next_derivation_index' => 60000,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now()->subDay(),
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $cursor = $this->walletCursorFor($owner, self::REALISTIC_TESTNET_TPUB);
        $this->assertSame(60000, (int) $cursor->next_derivation_index);
    }

    public function test_wallet_settings_update_clears_unsupported_state_when_primary_key_changes(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();
        $wallet = WalletSetting::create([
            'user_id' => $owner->id,
            'network' => 'testnet',
            'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            'onboarded_at' => now()->subDay(),
            'unsupported_configuration_active' => true,
            'unsupported_configuration_source' => 'proactive',
            'unsupported_configuration_reason' => 'outside_receive_activity',
            'unsupported_configuration_details' => 'Detected prior outside receive activity for this account.',
            'unsupported_configuration_flagged_at' => now()->subHour(),
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => 'vpub' . str_repeat('b', 40),
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $wallet->refresh();

        $this->assertFalse($wallet->unsupported_configuration_active);
        $this->assertNull($wallet->unsupported_configuration_source);
        $this->assertNull($wallet->unsupported_configuration_reason);
        $this->assertNull($wallet->unsupported_configuration_details);
        $this->assertNull($wallet->unsupported_configuration_flagged_at);
    }

    public function test_wallet_settings_update_flags_wallet_when_proactive_detection_finds_unknown_receive_activity(): void
    {
        Config::set('wallet.default_network', 'testnet');
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_count', 3);
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_cap', 3);
        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet/api');
        app()->forgetInstance(MempoolClient::class);

        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->times(4)
                ->andReturnUsing(function (string $xpub, int $index) {
                    return match ($index) {
                        0 => 'tb1qproactivescan0',
                        1 => 'tb1qproactivescan1',
                        2 => 'tb1qproactivescan2',
                        default => 'tb1qproactivescanx',
                    };
                });
        });

        Http::fake([
            'https://mempool.example/testnet/api/address/tb1qproactivescan0/txs' => Http::response([], 200),
            'https://mempool.example/testnet/api/address/tb1qproactivescan1/txs' => Http::response([
                [
                    'txid' => 'proactive-tx-1',
                    'vout' => [
                        [
                            'scriptpubkey_address' => 'tb1qproactivescan1',
                            'value' => 125000,
                        ],
                    ],
                ],
            ], 200),
            'https://mempool.example/testnet/api/address/tb1qproactivescan2/txs' => Http::response([], 200),
        ]);

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $wallet = WalletSetting::where('user_id', $owner->id)->firstOrFail();

        $this->assertTrue($wallet->unsupported_configuration_active);
        $this->assertSame('proactive', $wallet->unsupported_configuration_source);
        $this->assertSame('outside_receive_activity', $wallet->unsupported_configuration_reason);
        $this->assertStringContainsString('tb1qproactivescan1', (string) $wallet->unsupported_configuration_details);
        $this->assertStringContainsString('index 1', (string) $wallet->unsupported_configuration_details);
        $this->assertStringContainsString('proactive-tx-1', (string) $wallet->unsupported_configuration_details);
        $this->assertNotNull($wallet->unsupported_configuration_flagged_at);
    }

    public function test_wallet_settings_update_does_not_flag_wallet_for_known_invoice_receive_history(): void
    {
        Config::set('wallet.default_network', 'testnet');
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_count', 1);
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_cap', 1);
        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet/api');
        app()->forgetInstance(MempoolClient::class);

        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);
        $wallet = WalletSetting::create([
            'user_id' => $owner->id,
            'network' => 'testnet',
            'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            'onboarded_at' => now()->subDay(),
        ]);
        $fingerprint = app(WalletKeyLineage::class)->fingerprint('testnet', self::REALISTIC_TESTNET_TPUB);

        Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-KNOWN-HISTORY-1',
            'description' => 'Known invoice address',
            'amount_usd' => 100,
            'btc_rate' => 50000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qknowninvoice0',
            'derivation_index' => 0,
            'wallet_key_fingerprint' => $fingerprint,
            'wallet_network' => 'testnet',
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->times(2)
                ->andReturn('tb1qknowninvoice0');
        });

        Http::fake([
            'https://mempool.example/testnet/api/address/tb1qknowninvoice0/txs' => Http::response([
                [
                    'txid' => 'known-invoice-tx-1',
                    'vout' => [
                        [
                            'scriptpubkey_address' => 'tb1qknowninvoice0',
                            'value' => 125000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $wallet->refresh();

        $this->assertFalse($wallet->unsupported_configuration_active);
        $this->assertNull($wallet->unsupported_configuration_source);
        $this->assertNull($wallet->unsupported_configuration_reason);
        $this->assertNull($wallet->unsupported_configuration_details);
        $this->assertNull($wallet->unsupported_configuration_flagged_at);
    }

    public function test_wallet_settings_update_does_not_flag_wallet_for_spend_only_history(): void
    {
        Config::set('wallet.default_network', 'testnet');
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_count', 1);
        config()->set('blockchain.unsupported_wallet_detection.proactive_address_scan_cap', 1);
        config()->set('blockchain.mempool.testnet_base', 'https://mempool.example/testnet/api');
        app()->forgetInstance(MempoolClient::class);

        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->times(2)
                ->andReturn('tb1qspendonly0');
        });

        Http::fake([
            'https://mempool.example/testnet/api/address/tb1qspendonly0/txs' => Http::response([
                [
                    'txid' => 'spend-only-tx-1',
                    'vout' => [
                        [
                            'scriptpubkey_address' => 'tb1qsomeoneelse',
                            'value' => 125000,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $wallet = WalletSetting::where('user_id', $owner->id)->firstOrFail();

        $this->assertFalse($wallet->unsupported_configuration_active);
        $this->assertNull($wallet->unsupported_configuration_source);
        $this->assertNull($wallet->unsupported_configuration_reason);
        $this->assertNull($wallet->unsupported_configuration_details);
        $this->assertNull($wallet->unsupported_configuration_flagged_at);
    }

    public function test_wallet_settings_update_clamps_cursor_to_highest_assigned_invoice_index_plus_one(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();
        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        WalletSetting::create([
            'user_id' => $owner->id,
            'network' => 'testnet',
            'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            'onboarded_at' => now()->subDay(),
        ]);
        $fingerprint = app(WalletKeyLineage::class)->fingerprint('testnet', self::REALISTIC_TESTNET_TPUB);

        Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-NDI-CLAMP-1',
            'description' => 'clamp test',
            'amount_usd' => 100,
            'btc_rate' => 50000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qclampaddress000000000000000000001',
            'derivation_index' => 11,
            'wallet_key_fingerprint' => $fingerprint,
            'wallet_network' => 'testnet',
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
        ]);

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $cursor = $this->walletCursorFor($owner, self::REALISTIC_TESTNET_TPUB);
        $this->assertSame(12, (int) $cursor->next_derivation_index);
    }

    public function test_mainnet_rejects_testnet_wallet_key(): void
    {
        Config::set('wallet.default_network', 'mainnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')->never();
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => 'tpub' . str_repeat('a', 20),
            ]);

        $response->assertSessionHasErrors('bip84_xpub');
    }

    public function test_testnet_rejects_mainnet_wallet_key(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')->never();
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => 'xpub' . str_repeat('a', 20),
            ]);

        $response->assertSessionHasErrors('bip84_xpub');
    }

    public function test_wallet_settings_shows_wallet_key_helper_content(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit'));

        $response->assertOk();
        $response->assertSee('Where do I find this?', false);
        $response->assertDontSee('Recommended for setup', false);
        $response->assertSee('Ledger Live', false);
        $response->assertSee('Trezor Suite', false);
    }

    public function test_wallet_key_validation_endpoint_returns_preview_address(): void
    {
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qpreviewaddress0000000000000000000000');
        });

        $response = $this
            ->actingAs($owner)
            ->postJson(route('wallet.settings.validate'), [
                'bip84_xpub' => 'vpub' . str_repeat('a', 20),
            ]);

        $response->assertOk();
        $response->assertJson([
            'address' => 'tb1qpreviewaddress0000000000000000000000',
        ]);
    }

    public function test_wallet_key_validation_endpoint_rejects_invalid_key(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->postJson(route('wallet.settings.validate'), [
                'bip84_xpub' => 'not-a-key',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bip84_xpub']);
    }

    public function test_wallet_settings_preserves_input_on_invalid_key(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->from(route('wallet.settings.edit'))
            ->post(route('wallet.settings.update'), [
                'bip84_xpub' => 'not-a-key',
                'form_context' => 'primary',
            ]);

        $response->assertRedirect(route('wallet.settings.edit'));
        $response->assertSessionHasErrors('bip84_xpub');
        $response->assertSessionHasInput('bip84_xpub', 'not-a-key');
    }

    public function test_additional_wallet_rejects_wrong_network_key(): void
    {
        Config::set('wallet.default_network', 'mainnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')->never();
        });

        $response = $this
            ->actingAs($owner)
            ->post(route('wallet.settings.accounts.store'), [
                'label' => 'Treasury',
                'bip84_xpub' => 'tpub' . str_repeat('a', 20),
                'form_context' => 'additional',
            ]);

        $response->assertSessionHasErrors(['bip84_xpub'], null, 'walletAccount');
        $this->assertDatabaseMissing('user_wallet_accounts', [
            'user_id' => $owner->id,
            'label' => 'Treasury',
        ]);
    }

    public function test_additional_wallet_accepts_realistic_testnet_wallet_key_length(): void
    {
        Config::set('wallet.default_network', 'testnet');
        $owner = User::factory()->create();

        $this->mock(HdWallet::class, function ($mock) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->andReturn('tb1qtestaddress00000000000000000000000');
        });

        $this
            ->actingAs($owner)
            ->post(route('wallet.settings.accounts.store'), [
                'label' => 'Recovered account',
                'bip84_xpub' => self::REALISTIC_TESTNET_TPUB,
            ])
            ->assertRedirect(route('wallet.settings.edit'));

        $account = UserWalletAccount::where('user_id', $owner->id)->firstOrFail();
        $raw = DB::table('user_wallet_accounts')->where('id', $account->id)->value('bip84_xpub');

        $this->assertSame(self::REALISTIC_TESTNET_TPUB, $account->bip84_xpub);
        $this->assertNotSame(self::REALISTIC_TESTNET_TPUB, $raw);
        $this->assertGreaterThan(255, strlen($raw));
    }

    private function createWalletSetting(User $user): WalletSetting
    {
        return WalletSetting::create([
            'user_id' => $user->id,
            'network' => 'testnet',
            'bip84_xpub' => 'vpub-test-key',
        ]);
    }

    private function walletCursorFor(User $user, string $xpub, string $network = 'testnet'): WalletKeyCursor
    {
        return WalletKeyCursor::query()
            ->where('user_id', $user->id)
            ->where('network', $network)
            ->where('key_fingerprint', $this->walletFingerprint($network, $xpub))
            ->firstOrFail();
    }

    private function walletFingerprint(string $network, string $xpub): string
    {
        return hash('sha256', strtolower(trim($network)) . '|' . preg_replace('/\s+/', '', trim($xpub)));
    }

    private function invoiceNumberCollisionException(): QueryException
    {
        $previous = new PDOException('UNIQUE constraint failed: invoices.user_id, invoices.number');
        $previous->errorInfo = ['23000', null, 'UNIQUE constraint failed: invoices.user_id, invoices.number'];

        return new QueryException(
            'sqlite',
            'insert into "invoices" ("user_id", "number") values (?, ?)',
            [],
            $previous
        );
    }
}
