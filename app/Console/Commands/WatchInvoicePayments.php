<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\BtcRate;
use App\Services\InvoicePaymentDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WatchInvoicePayments extends Command
{
    protected $signature = 'wallet:watch-payments {--invoice= : Limit to a specific invoice ID}';

    protected $description = 'Poll known invoice addresses for payments and auto-mark invoices paid.';

    public function __construct(private readonly InvoicePaymentDetector $detector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Invoice::query()
            ->with(['user.walletSetting'])
            ->whereNotNull('payment_address')
            ->whereHas('user.walletSetting')
            ->where('status', '!=', 'void')
            ->orderBy('id');

        if ($invoiceId = $this->option('invoice')) {
            $query->where('id', $invoiceId);
        }

        $processed = 0;
        $updated = 0;

        $query->chunkById(50, function ($invoices) use (&$processed, &$updated) {
            foreach ($invoices as $invoice) {
                $processed++;
                $wallet = $invoice->user->walletSetting;
                if (!$wallet) {
                    $this->warn("Invoice {$invoice->id} has no wallet setting.");
                    continue;
                }

                $result = $this->detector->detect($invoice, $wallet->network);
                if (!$result) {
                    continue;
                }

                $this->recordPayment($invoice, $result);
                $updated++;
            }
        });

        $this->info("Processed {$processed} invoices, updated {$updated}.");

        return Command::SUCCESS;
    }

    private function recordPayment(Invoice $invoice, array $result): void
    {
        DB::transaction(function () use ($invoice, $result) {
            $payment = InvoicePayment::firstOrNew([
                'invoice_id' => $invoice->id,
                'txid' => $result['txid'],
            ]);

            if (!$payment->exists) {
                $rate = BtcRate::current();
                $usdRate = $rate['rate_usd'] ?? null;
                $fiatAmount = $usdRate ? round(($result['sats'] / Invoice::SATS_PER_BTC) * (float) $usdRate, 2) : null;

                $payment->fill([
                    'sats_received' => $result['sats'],
                    'detected_at' => $result['detected_at'],
                    'usd_rate' => $usdRate,
                    'fiat_amount' => $fiatAmount,
                ]);
            } else {
                $payment->sats_received = $result['sats'];
            }

            $payment->block_height = $result['block_height'];
            if ($result['confirmed'] && ($result['confirmed_at'] ?? null)) {
                $payment->confirmed_at = $result['confirmed_at'];
            }

            $payment->save();

            $invoice->payment_amount_sat = $invoice->payments()->sum('sats_received');
            $invoice->txid = $result['txid'];
            $invoice->payment_confirmations = $result['confirmations'];
            $invoice->payment_confirmed_height = $result['block_height'];
            $invoice->payment_detected_at = $invoice->payment_detected_at ?: $result['detected_at'];
            if ($result['confirmed'] && ($result['confirmed_at'] ?? null)) {
                $invoice->payment_confirmed_at = $result['confirmed_at'];
            }

            $invoice->refreshPaymentState($result['confirmed_at'] ?? $result['detected_at']);
        });

        $invoice->refresh();
        $paidSats = $invoice->payment_amount_sat ?? 0;
        $status = strtoupper($invoice->status ?? 'sent');
        $this->info("Invoice {$invoice->id} {$status}: {$result['txid']} ({$result['sats']} sats, total {$paidSats}).");
    }
}
