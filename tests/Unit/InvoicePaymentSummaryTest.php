<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_outstanding_sats_clamped_to_zero_when_usd_settled(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'user_id' => $user->id,
            'name' => 'Acme',
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
}
