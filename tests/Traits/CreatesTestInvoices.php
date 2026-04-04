<?php

namespace Tests\Traits;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Services\WalletKeyLineage;
use Illuminate\Support\Carbon;

trait CreatesTestInvoices
{
    protected int $invoiceSequence = 0;

    /**
     * Create a user (if not supplied), a client, and a draft invoice.
     * Returns the persisted Invoice.
     */
    protected function makeInvoice(?User $owner = null, array $overrides = []): Invoice
    {
        $owner ??= User::factory()->create();

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        $this->invoiceSequence++;

        $defaults = [
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-' . str_pad((string) $this->invoiceSequence, 4, '0', STR_PAD_LEFT),
            'description' => 'General services',
            'amount_usd' => 500,
            'btc_rate' => 50_000,
            'amount_btc' => 0.01,
            'payment_address' => 'tb1qw508d6qejxtdg4y5r3zarvary0c5xw7k3l0zz',
            'status' => 'draft',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ];

        $invoice = Invoice::create($defaults);

        if (!empty($overrides)) {
            $invoice->forceFill($overrides)->save();
        }

        return $invoice->refresh();
    }

    /**
     * Create a wallet-backed user and a sent invoice on the given network.
     * Used by payment-watcher tests that need lineage tracking.
     */
    protected function makeInvoiceWithNetwork(string $network, ?User $owner = null): Invoice
    {
        $owner ??= User::factory()->create();

        $owner->walletSetting()->create([
            'network' => $network,
            'bip84_xpub' => 'tpubD6Nz...',
        ]);

        $client = Client::create([
            'user_id' => $owner->id,
            'name' => 'Acme',
            'email' => 'billing@example.com',
        ]);

        $this->invoiceSequence++;

        return Invoice::create([
            'user_id' => $owner->id,
            'client_id' => $client->id,
            'number' => 'INV-' . str_pad((string) $this->invoiceSequence, 4, '0', STR_PAD_LEFT),
            'description' => 'Consulting',
            'amount_usd' => 400,
            'btc_rate' => 40_000,
            'amount_btc' => 0.01,
            'payment_address' => 'tb1qq0exampleaddress0000000000000',
            'wallet_key_fingerprint' => app(WalletKeyLineage::class)->fingerprint($network, 'tpubD6Nz...'),
            'wallet_network' => $network,
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
    }
}
