<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\BtcRate;
use App\Services\InvoiceAlertService;
use App\Services\InvoicePaymentDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WatchInvoicePayments extends Command
{
    protected $signature = 'wallet:watch-payments {--invoice= : Limit to a specific invoice ID}';

    protected $description = 'Poll known invoice addresses for payments and auto-mark invoices paid.';

    public function __construct(
        private readonly InvoicePaymentDetector $detector,
        private readonly InvoiceAlertService $alerts
    )
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

                $results = $this->detector->detectPayments($invoice, $wallet->network);
                if (empty($results)) {
                    continue;
                }

                $this->recordPayments($invoice, $results);
                $this->alerts->checkPaymentThresholds($invoice->fresh('payments'));
                $updated += count($results);
            }
        });

        $this->info("Processed {$processed} invoices, updated {$updated}.");

        return Command::SUCCESS;
    }

    private function recordPayments(Invoice $invoice, array $results): void
    {
        $logs = DB::transaction(function () use ($invoice, $results) {
            $logs = [];
            $latestReference = null;

            foreach ($results as $result) {
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
                    $payment->detected_at = $payment->detected_at ?: $result['detected_at'];
                }

                $payment->block_height = $result['block_height'];
                if ($result['confirmed'] && ($result['confirmed_at'] ?? null)) {
                    $payment->confirmed_at = $result['confirmed_at'];
                }

                $payment->save();

                $reference = $result['confirmed_at'] ?? $result['detected_at'];
                if ($reference && (!$latestReference || $reference->gt($latestReference))) {
                    $latestReference = $reference;
                }

                $logs[] = [
                    'txid' => $result['txid'],
                    'sats' => $result['sats'],
                ];
            }

            $invoice->payment_amount_sat = $invoice->payments()->sum('sats_received');
            $lastResult = end($results) ?: null;
            if ($lastResult) {
                $invoice->txid = $lastResult['txid'];
                $invoice->payment_confirmations = $lastResult['confirmations'];
                $invoice->payment_confirmed_height = $lastResult['block_height'];
                $invoice->payment_detected_at = $invoice->payment_detected_at ?: $lastResult['detected_at'];
                if ($lastResult['confirmed'] && ($lastResult['confirmed_at'] ?? null)) {
                    $invoice->payment_confirmed_at = $lastResult['confirmed_at'];
                }
            }

            $invoice->refreshPaymentState($latestReference);

            return $logs;
        });

        $invoice->refresh();
        $paidSats = $invoice->payment_amount_sat ?? 0;
        $status = strtoupper($invoice->status ?? 'sent');
        $outstanding = $invoice->outstanding_sats;

        foreach ($logs as $log) {
            $this->info("Invoice {$invoice->id} {$status}: {$log['txid']} ({$log['sats']} sats, total {$paidSats}).");
            Log::info('invoice.payment.detected', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status,
                'txid' => $log['txid'],
                'sats' => $log['sats'],
                'paid_sats' => $paidSats,
                'outstanding_sats' => $outstanding,
            ]);
        }
    }
}
