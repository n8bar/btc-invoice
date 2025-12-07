<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\UserWalletAccount;
use App\Models\WalletSetting;
use App\Services\HdWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_can_add_and_remove_additional_wallet_accounts(): void
    {
        $owner = User::factory()->create();

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
