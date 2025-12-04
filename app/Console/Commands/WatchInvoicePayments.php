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
                $hasUnconfirmed = $invoice->payments()
                    ->whereNull('confirmed_at')
                    ->where('is_adjustment', false)
                    ->exists();

                if (empty($results) && !$hasUnconfirmed) {
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
        $droppedCount = 0;
        $logs = DB::transaction(function () use (&$droppedCount, $invoice, $results) {
            $logs = [];
            $latestReference = null;
            $rate = BtcRate::current();
            $usdRate = $rate['rate_usd'] ?? null;
            if (!$usdRate && $invoice->btc_rate) {
                $usdRate = (float) $invoice->btc_rate;
            }
            $requiredConfirmations = (int) config('blockchain.confirmations_required', 1);
            $liveTxids = collect($results)->pluck('txid')->filter()->all();

            $droppedCount = InvoicePayment::query()
                ->where('invoice_id', $invoice->id)
                ->whereNull('confirmed_at')
                ->where('is_adjustment', false)
                ->when(!empty($liveTxids), fn ($query) => $query->whereNotIn('txid', $liveTxids))
                ->delete();

            foreach ($results as $result) {
                $payment = InvoicePayment::firstOrNew([
                    'invoice_id' => $invoice->id,
                    'txid' => $result['txid'],
                ]);

                $isConfirmed = ($result['confirmed'] ?? false)
                    && (($result['confirmations'] ?? 0) >= $requiredConfirmations);

                $payment->sats_received = $result['sats'];
                $payment->detected_at = $payment->detected_at ?: $result['detected_at'];

                if ($payment->usd_rate !== null) {
                    $payment->fiat_amount = round(
                        ($result['sats'] / Invoice::SATS_PER_BTC) * (float) $payment->usd_rate,
                        2
                    );
                } elseif ($usdRate) {
                    $payment->usd_rate = $usdRate;
                    $payment->fiat_amount = round(
                        ($result['sats'] / Invoice::SATS_PER_BTC) * (float) $usdRate,
                        2
                    );
                }

                $payment->block_height = $result['block_height'];
                if ($isConfirmed && ($result['confirmed_at'] ?? null)) {
                    $payment->confirmed_at = $payment->confirmed_at ?: $result['confirmed_at'];
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

            $invoice->load('payments');
            $invoice->payment_amount_sat = $invoice->sumPaymentSats(true);

            $latestConfirmed = $invoice->payments
                ->filter(fn (InvoicePayment $payment) => $payment->confirmed_at !== null)
                ->sortBy('confirmed_at')
                ->last();

            $latestDetected = $invoice->payments->sortBy('detected_at')->last();

            if ($invoice->payments->isEmpty()) {
                $invoice->txid = null;
                $invoice->payment_confirmations = 0;
                $invoice->payment_confirmed_height = null;
                $invoice->payment_detected_at = null;
                $invoice->payment_confirmed_at = null;
            } else {
                $invoice->txid = $latestConfirmed?->txid ?? $latestDetected?->txid ?? $invoice->txid;
                $maxConfirmations = collect($results)->max('confirmations');
                $invoice->payment_confirmations = $maxConfirmations !== null
                    ? $maxConfirmations
                    : ($invoice->payment_confirmations ?? 0);
                $invoice->payment_confirmed_height = $latestConfirmed?->block_height;
                $invoice->payment_detected_at = $invoice->payment_detected_at ?: $latestDetected?->detected_at;
                if ($latestConfirmed?->confirmed_at) {
                    $invoice->payment_confirmed_at = $latestConfirmed->confirmed_at;
                }
            }

            $invoice->refreshPaymentState($latestReference);

            return $logs;
        });

        $invoice->refresh();
        $paidSats = $invoice->payment_amount_sat ?? 0;
        $status = strtoupper($invoice->status ?? 'sent');
        $outstanding = $invoice->outstanding_sats;

        if ($droppedCount > 0) {
            Log::info('invoice.payment.dropped', [
                'invoice_id' => $invoice->id,
                'count' => $droppedCount,
            ]);
        }

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
