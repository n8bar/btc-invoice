<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoicePaymentSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_outstanding_sats_clamped_to_zero_when_usd_settled(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-1002',
            'amount_usd' => 250,
            'btc_rate' => 91726.24,
            'amount_btc' => round(250 / 91726.24, 8),
            'payment_address' => 'tb1qexample',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-main',
            'sats_received' => 271745,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 91916.80,
            'fiat_amount' => 249.78,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-adjust',
            'sats_received' => 247,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 89179.89,
            'fiat_amount' => 0.22,
            'is_adjustment' => true,
        ]);

        $invoice->refresh()->refreshPaymentState();
        $summary = $invoice->paymentSummary(['rate_usd' => 91726.24]);

        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0, $summary['outstanding_sats']);
        $this->assertSame(0.0, $summary['outstanding_usd']);
    }

    public function test_ignored_payments_are_excluded_from_summary_math(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-1003',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qexample',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-active',
            'sats_received' => 100_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 50.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'tx-ignored',
            'sats_received' => 100_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 50.00,
            'ignored_at' => now(),
            'ignore_reason' => 'Wrong invoice',
        ]);

        $invoice->refresh()->refreshPaymentLedger();
        $summary = $invoice->paymentSummary(['rate_usd' => 50_000]);

        $this->assertSame('partial', $invoice->status);
        $this->assertSame(100_000, $summary['confirmed_sats']);
        $this->assertSame(50.0, $summary['confirmed_usd']);
        $this->assertSame(100_000, $summary['outstanding_sats']);
        $this->assertSame(50.0, $summary['outstanding_usd']);
    }

    public function test_reattributed_payments_count_only_on_the_destination_invoice(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
            'email' => 'billing@acme.test',
        ]);

        $sourceInvoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-2001',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qsourceexample',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        $destinationInvoice = Invoice::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'number' => 'INV-2002',
            'amount_usd' => 100,
            'btc_rate' => 50_000,
            'amount_btc' => 0.002,
            'payment_address' => 'tb1qdestinationexample',
            'status' => 'sent',
            'invoice_date' => now()->toDateString(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $sourceInvoice->id,
            'accounting_invoice_id' => $destinationInvoice->id,
            'txid' => 'tx-reattributed-summary',
            'sats_received' => 200_000,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => 50_000,
            'fiat_amount' => 100.00,
            'reattributed_at' => now(),
            'reattributed_by_user_id' => $user->id,
            'reattribute_reason' => 'Belonged to newer invoice',
        ]);

        $sourceInvoice->refresh()->refreshPaymentLedger();
        $destinationInvoice->refresh()->refreshPaymentLedger();
        $sourceSummary = $sourceInvoice->fresh()->paymentSummary(['rate_usd' => 50_000]);
        $destinationSummary = $destinationInvoice->fresh()->paymentSummary(['rate_usd' => 50_000]);

        $this->assertSame('sent', $sourceInvoice->fresh()->status);
        $this->assertSame(0, $sourceSummary['confirmed_sats']);
        $this->assertSame(0.0, $sourceSummary['confirmed_usd']);
        $this->assertSame(200_000, $sourceSummary['outstanding_sats']);
        $this->assertSame(100.0, $sourceSummary['outstanding_usd']);

        $this->assertSame('paid', $destinationInvoice->fresh()->status);
        $this->assertSame(200_000, $destinationSummary['confirmed_sats']);
        $this->assertSame(100.0, $destinationSummary['confirmed_usd']);
        $this->assertSame(0, $destinationSummary['outstanding_sats']);
        $this->assertSame(0.0, $destinationSummary['outstanding_usd']);
    }
}
