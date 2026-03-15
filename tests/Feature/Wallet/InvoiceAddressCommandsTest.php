<?php

namespace Tests\Feature\Wallet;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\WalletKeyCursor;
use App\Models\WalletSetting;
use App\Services\HdWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAddressCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_command_uses_cursor_ledger_and_persists_invoice_lineage(): void
    {
        $user = User::factory()->create();
        $wallet = $this->createWalletSetting($user, 'vpub-assign-key');
        $client = $this->createClient($user);

        $first = $this->createInvoice($user, $client, 'INV-ASSIGN-1');
        $second = $this->createInvoice($user, $client, 'INV-ASSIGN-2');

        $this->mock(HdWallet::class, function ($mock) use ($wallet) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->with($wallet->bip84_xpub, 0, 'testnet')
                ->andReturn('tb1qassign00000000000000000000000000000');
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->with($wallet->bip84_xpub, 1, 'testnet')
                ->andReturn('tb1qassign11111111111111111111111111111');
        });

        $this->artisan('wallet:assign-invoice-addresses')->assertExitCode(0);

        $first->refresh();
        $second->refresh();
        $fingerprint = $this->walletFingerprint('testnet', 'vpub-assign-key');

        $this->assertSame('tb1qassign00000000000000000000000000000', $first->payment_address);
        $this->assertSame(0, (int) $first->derivation_index);
        $this->assertSame($fingerprint, $first->wallet_key_fingerprint);
        $this->assertSame('testnet', $first->wallet_network);

        $this->assertSame('tb1qassign11111111111111111111111111111', $second->payment_address);
        $this->assertSame(1, (int) $second->derivation_index);
        $this->assertSame($fingerprint, $second->wallet_key_fingerprint);
        $this->assertSame('testnet', $second->wallet_network);

        $cursor = $this->walletCursorFor($user, 'vpub-assign-key');
        $this->assertSame(2, (int) $cursor->next_derivation_index);
    }

    public function test_reassign_command_sets_lineage_when_address_already_matches(): void
    {
        $user = User::factory()->create();
        $wallet = $this->createWalletSetting($user, 'vpub-reassign-key');
        $client = $this->createClient($user);

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-REASSIGN-MATCH',
            'description' => 'Existing address',
            'amount_usd' => 50,
            'btc_rate' => 50_000,
            'amount_btc' => 0.001,
            'payment_address' => 'tb1qmatchedaddress0000000000000000000',
            'derivation_index' => 7,
            'status' => 'sent',
            'invoice_date' => '2025-01-01',
        ]);

        $this->mock(HdWallet::class, function ($mock) use ($wallet) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->with($wallet->bip84_xpub, 7, 'testnet')
                ->andReturn('tb1qmatchedaddress0000000000000000000');
        });

        $this->artisan('wallet:reassign-invoice-addresses', [
            '--invoice' => $invoice->id,
            '--apply' => true,
        ])->assertExitCode(0);

        $invoice->refresh();

        $this->assertSame('tb1qmatchedaddress0000000000000000000', $invoice->payment_address);
        $this->assertSame(7, (int) $invoice->derivation_index);
        $this->assertSame('testnet', $invoice->wallet_network);
        $this->assertSame($this->walletFingerprint('testnet', 'vpub-reassign-key'), $invoice->wallet_key_fingerprint);
    }

    public function test_reassign_command_use_next_index_advances_cursor_ledger(): void
    {
        $user = User::factory()->create();
        $wallet = $this->createWalletSetting($user, 'vpub-next-index-key');
        $client = $this->createClient($user);
        $fingerprint = $this->walletFingerprint('testnet', 'vpub-next-index-key');

        WalletKeyCursor::create([
            'user_id' => $user->id,
            'network' => 'testnet',
            'key_fingerprint' => $fingerprint,
            'next_derivation_index' => 3,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-REASSIGN-NEXT',
            'description' => 'Cursor advance',
            'amount_usd' => 50,
            'btc_rate' => 50_000,
            'amount_btc' => 0.001,
            'payment_address' => 'tb1qlegacyaddress000000000000000000000',
            'derivation_index' => 1,
            'wallet_key_fingerprint' => $fingerprint,
            'wallet_network' => 'testnet',
            'status' => 'sent',
            'invoice_date' => '2025-01-01',
        ]);

        $this->mock(HdWallet::class, function ($mock) use ($wallet) {
            $mock->shouldReceive('deriveAddress')
                ->once()
                ->with($wallet->bip84_xpub, 3, 'testnet')
                ->andReturn('tb1qreassigned3333333333333333333333333');
        });

        $this->artisan('wallet:reassign-invoice-addresses', [
            '--invoice' => $invoice->id,
            '--apply' => true,
            '--use-next-index' => true,
        ])->assertExitCode(0);

        $invoice->refresh();

        $this->assertSame('tb1qreassigned3333333333333333333333333', $invoice->payment_address);
        $this->assertSame(3, (int) $invoice->derivation_index);
        $this->assertSame('testnet', $invoice->wallet_network);
        $this->assertSame($fingerprint, $invoice->wallet_key_fingerprint);
        $this->assertSame(4, (int) $this->walletCursorFor($user, 'vpub-next-index-key')->next_derivation_index);
    }

    private function createWalletSetting(User $user, string $xpub): WalletSetting
    {
        return WalletSetting::create([
            'user_id' => $user->id,
            'network' => 'testnet',
            'bip84_xpub' => $xpub,
            'onboarded_at' => now(),
        ]);
    }

    private function createClient(User $user): Client
    {
        return Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => 'billing+' . $user->id . '@example.test',
        ]);
    }

    private function createInvoice(User $user, Client $client, string $number): Invoice
    {
        return Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => $number,
            'description' => 'Needs address',
            'amount_usd' => 50,
            'btc_rate' => 50_000,
            'amount_btc' => 0.001,
            'status' => 'draft',
            'invoice_date' => '2025-01-01',
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
}
