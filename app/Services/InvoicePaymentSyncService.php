<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicePaymentSyncService
{
    public function __construct(
        private readonly InvoicePaymentDetector $detector,
        private readonly InvoiceAlertService $alerts,
        private readonly UnsupportedConfigurationEvidenceService $unsupportedEvidence,
    ) {
    }

    /**
     * @return array{
     *   processed: bool,
     *   updated: int,
     *   dropped: int,
     *   payments: array<int, array{txid: string, sats: int}>,
     *   status: string|null,
     *   paid_sats: int,
     *   outstanding_sats: int|null
     * }
     */
    public function syncInvoice(
        Invoice $invoice,
        ?string $network = null,
        bool $force = false,
        bool $checkAlerts = true
    ): array {
        if (! $invoice->payment_address || $invoice->status === 'void') {
            return $this->emptyResult(false, $invoice);
        }

        $resolvedNetwork = $network;
        if (! $resolvedNetwork) {
            $resolvedNetwork = $invoice->wallet_network;
        }

        if (! $resolvedNetwork) {
            return $this->emptyResult(false, $invoice);
        }

        if (! $force) {
            $ttlSeconds = $this->throttleSeconds();
            if ($ttlSeconds > 0) {
                $lockKey = $this->throttleKey($invoice->id);
                if (! Cache::add($lockKey, true, now()->addSeconds($ttlSeconds))) {
                    return $this->emptyResult(false, $invoice);
                }
            }
        }

        try {
            $results = $this->detector->detectPayments($invoice, $resolvedNetwork);
        } catch (\Throwable $e) {
            Log::warning('invoice.payment.sync.detect_failed', [
                'invoice_id' => $invoice->id,
                'network' => $resolvedNetwork,
                'error' => $e->getMessage(),
            ]);

            return $this->emptyResult(false, $invoice);
        }

        $hasUnconfirmed = $invoice->payments()
            ->whereNull('ignored_at')
            ->whereNull('confirmed_at')
            ->where('is_adjustment', false)
            ->exists();

        if (empty($results) && ! $hasUnconfirmed) {
            return $this->emptyResult(true, $invoice);
        }

        return $this->recordPayments($invoice, $results, $checkAlerts);
    }

    /**
     * @param  array<int, array{
     *     txid: string,
     *     sats: int,
     *     confirmed: bool,
     *     confirmations: int,
     *     block_height: int|null,
     *     detected_at: \Illuminate\Support\Carbon,
     *     confirmed_at: \Illuminate\Support\Carbon|null
     * }>  $results
     * @return array{
     *   processed: bool,
     *   updated: int,
     *   dropped: int,
     *   payments: array<int, array{txid: string, sats: int}>,
     *   status: string|null,
     *   paid_sats: int,
     *   outstanding_sats: int|null
     * }
     */
    private function recordPayments(Invoice $invoice, array $results, bool $checkAlerts): array
    {
        $droppedCount = 0;
        $logs = DB::transaction(function () use (&$droppedCount, $invoice, $results) {
            $logs = [];
            $latestReference = null;
            $rate = BtcRate::current();
            $usdRate = $rate['rate_usd'] ?? null;
            if (! $usdRate && $invoice->btc_rate) {
                $usdRate = (float) $invoice->btc_rate;
            }
            $requiredConfirmations = (int) config('blockchain.confirmations_required', 1);
            $liveTxids = collect($results)->pluck('txid')->filter()->all();

            $droppedCount = InvoicePayment::query()
                ->where('invoice_id', $invoice->id)
                ->whereNull('ignored_at')
                ->whereNull('confirmed_at')
                ->where('is_adjustment', false)
                ->when(! empty($liveTxids), fn ($query) => $query->whereNotIn('txid', $liveTxids))
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
                $payment->meta = array_merge($payment->meta ?? [], [
                    'confirmations' => max((int) ($result['confirmations'] ?? 0), 0),
                ]);

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
                if ($reference && (! $latestReference || $reference->gt($latestReference))) {
                    $latestReference = $reference;
                }

                $logs[] = [
                    'txid' => $result['txid'],
                    'sats' => $result['sats'],
                ];
            }

            $invoice->refreshPaymentLedger($latestReference);

            return $logs;
        });

        if ($checkAlerts) {
            $this->alerts->checkPaymentThresholds($invoice->fresh('payments'));
        }

        $this->unsupportedEvidence->flagPaymentCollisionEvidence($invoice->fresh(), $logs);

        $invoice->refresh();

        if ($droppedCount > 0) {
            Log::info('invoice.payment.dropped', [
                'invoice_id' => $invoice->id,
                'count' => $droppedCount,
            ]);
        }

        foreach ($logs as $log) {
            Log::info('invoice.payment.detected', [
                'invoice_id' => $invoice->id,
                'status' => $invoice->status,
                'txid' => $log['txid'],
                'sats' => $log['sats'],
                'paid_sats' => $invoice->payment_amount_sat ?? 0,
                'outstanding_sats' => $invoice->outstanding_sats,
            ]);
        }

        return [
            'processed' => true,
            'updated' => count($results),
            'dropped' => $droppedCount,
            'payments' => $logs,
            'status' => $invoice->status,
            'paid_sats' => (int) ($invoice->payment_amount_sat ?? 0),
            'outstanding_sats' => $invoice->outstanding_sats,
        ];
    }

    /**
     * @return array{
     *   processed: bool,
     *   updated: int,
     *   dropped: int,
     *   payments: array<int, array{txid: string, sats: int}>,
     *   status: string|null,
     *   paid_sats: int,
     *   outstanding_sats: int|null
     * }
     */
    private function emptyResult(bool $processed, Invoice $invoice): array
    {
        return [
            'processed' => $processed,
            'updated' => 0,
            'dropped' => 0,
            'payments' => [],
            'status' => $invoice->status,
            'paid_sats' => (int) ($invoice->payment_amount_sat ?? 0),
            'outstanding_sats' => $invoice->outstanding_sats,
        ];
    }

    private function throttleKey(int $invoiceId): string
    {
        return "invoice-payment-sync:{$invoiceId}";
    }

    private function throttleSeconds(): int
    {
        return max((int) config('blockchain.getting_started_sync.throttle_seconds', 60), 0);
    }
}
