<?php

namespace Tests\Feature\Console;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BackfillInvoicePaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_payment_row_when_missing(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->forceFill([
            'txid' => 'legacy-123',
            'payment_amount_sat' => 500_000,
            'payment_detected_at' => Carbon::parse('2025-01-05 10:00:00', 'UTC'),
            'payment_confirmed_at' => Carbon::parse('2025-01-05 10:05:00', 'UTC'),
            'payment_confirmed_height' => 250500,
            'btc_rate' => 40_000,
        ])->save();

        $this->artisan('wallet:backfill-payments')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'legacy-123',
            'sats_received' => 500_000,
            'block_height' => 250500,
        ]);
    }

    public function test_backfill_skips_invoices_with_existing_payments(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->forceFill([
            'txid' => 'legacy-456',
            'payment_amount_sat' => 600_000,
            'btc_rate' => 39_000,
        ])->save();

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'existing',
            'sats_received' => 600_000,
            'detected_at' => Carbon::now(),
        ]);

        $this->artisan('wallet:backfill-payments')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'legacy-456',
        ]);
        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'txid' => 'existing',
        ]);
    }

    private function makeInvoice(): Invoice
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme Co',
            'email' => 'billing@example.com',
        ]);

        return Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-' . uniqid(),
            'description' => 'Legacy test',
            'amount_usd' => 500,
            'btc_rate' => 40_000,
            'amount_btc' => 0.0125,
            'payment_address' => 'tb1qq0examplelegacy',
            'status' => 'sent',
            'invoice_date' => Carbon::now()->toDateString(),
            'due_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
    }
}
