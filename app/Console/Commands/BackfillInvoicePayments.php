<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Console\Command;

class BackfillInvoicePayments extends Command
{
    protected $signature = 'wallet:backfill-payments';

    protected $description = 'Create invoice_payments rows from legacy invoice payment metadata.';

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        Invoice::query()
            ->whereNotNull('txid')
            ->whereNotNull('payment_amount_sat')
            ->chunkById(100, function ($invoices) use (&$created, &$skipped) {
                foreach ($invoices as $invoice) {
                    if ($invoice->payments()->exists()) {
                        $skipped++;
                        continue;
                    }

                    $fiatAmount = $this->fiatAmount($invoice);

                    $payment = InvoicePayment::firstOrCreate([
                        'invoice_id' => $invoice->id,
                        'txid' => $invoice->txid,
                    ], [
                        'sats_received' => $invoice->payment_amount_sat,
                        'detected_at' => $invoice->payment_detected_at,
                        'confirmed_at' => $invoice->payment_confirmed_at,
                        'block_height' => $invoice->payment_confirmed_height,
                        'usd_rate' => $invoice->btc_rate,
                        'fiat_amount' => $fiatAmount,
                    ]);

                    if ($payment->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            });

        $summary = "Backfilled {$created} payments";
        if ($skipped > 0) {
            $summary .= ", skipped {$skipped}";
        }

        $this->info($summary . '.');

        return Command::SUCCESS;
    }

    private function fiatAmount(Invoice $invoice): ?float
    {
        if (empty($invoice->payment_amount_sat) || empty($invoice->btc_rate)) {
            return null;
        }

        return round(($invoice->payment_amount_sat / Invoice::SATS_PER_BTC) * (float) $invoice->btc_rate, 2);
    }
}
