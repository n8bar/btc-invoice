<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserWalletAccount;
use App\Models\WalletSetting;
use App\Services\BtcRate;
use App\Services\HdWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const REALISTIC_TESTNET_TPUB = 'tpubDCMX5n5xeyKFQ1R98FTjQ21An9e2SgN8gF5pa4DJNfQd8B5CYCqkkWXEmH4YrxRAEDzFSv25yineuGfvFAg9tWJcGakvm7Ft5e41jQZ2bHk';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('wallet.default_network', 'testnet');
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
    }

    public function test_invoice_create_prefills_defaults(): void
    {
        $owner = User::factory()->create([
            'invoice_default_description' => 'Consulting retainer',
            'invoice_default_terms_days' => 7,
        ]);
        $this->createWalletSetting($owner);

        $response = $this
            ->actingAs($owner)
            ->get(route('invoices.create'));

        $response->assertOk();
        $response->assertSee('Consulting retainer', false);
        $expectedDue = now()->addDays(7)->toDateString();
        $response->assertSee('value="' . $expectedDue . '"', false);
    }

    public function test_invoice_create_prefill_rate_is_rounded_to_two_decimals(): void
    {
        $owner = User::factory()->create();
        $this->createWalletSetting($owner);

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
        $owner = User::factory()->create();

        $this
            ->actingAs($owner)
            ->patch(route('settings.invoice.update'), [
                'branding_heading' => 'CryptoZing Invoice',
                'billing_name' => 'CryptoZing LLC',
                'billing_email' => 'billing@cryptozing.app',
                'invoice_default_description' => 'Weekly retainer',
                'invoice_default_terms_days' => 14,
            ])
            ->assertRedirect(route('settings.invoice.edit'));

        $owner->refresh();
        $this->assertSame('Weekly retainer', $owner->invoice_default_description);
        $this->assertSame(14, $owner->invoice_default_terms_days);
        $this->assertSame('CryptoZing Invoice', $owner->branding_heading);
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
            'next_derivation_index' => 0,
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

    public function test_wallet_settings_shows_getting_started_progress_strip_when_context_flag_present(): void
    {
        $owner = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->get(route('wallet.settings.edit', ['getting_started' => 1]));

        $response->assertOk();
        $response->assertSee('Back to welcome', false);
        $response->assertSee(route('getting-started.welcome'), false);
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
            'next_derivation_index' => 0,
        ]);
    }
}
