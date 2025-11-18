<?php

namespace App\Services;

use App\Models\Invoice;
use App\Services\Blockchain\MempoolClient;
use Illuminate\Support\Carbon;

class InvoicePaymentDetector
{
    public function __construct(
        private readonly MempoolClient $mempoolClient
    ) {
    }

    /**
     * Detect all payments for an invoice address.
     *
     * @return array<int, array{
     *     txid: string,
     *     sats: int,
     *     confirmed: bool,
     *     confirmations: int,
     *     block_height: int|null,
     *     detected_at: \Illuminate\Support\Carbon,
     *     confirmed_at: \Illuminate\Support\Carbon|null
     * }>
     */
    public function detectPayments(Invoice $invoice, string $network): array
    {
        $address = $invoice->payment_address;
        if (!$address) {
            return [];
        }

        $transactions = $this->mempoolClient->transactions($network, $address);
        if (empty($transactions)) {
            return [];
        }

        $payments = [];
        $tipHeight = null;

        foreach ($transactions as $tx) {
            $received = $this->satsReceivedForAddress($tx, $address);
            if ($received <= 0) {
                continue;
            }

            $status = $tx['status'] ?? [];
            $confirmed = (bool) ($status['confirmed'] ?? false);
            $blockHeight = $status['block_height'] ?? null;
            $detectedAt = $this->detectedAtFromStatus($status);
            $confirmedAt = null;
            $confirmations = 0;

            if ($confirmed && $blockHeight) {
                if ($tipHeight === null) {
                    $tipHeight = $this->mempoolClient->tipHeight($network);
                }

                $confirmations = $tipHeight && $tipHeight >= $blockHeight
                    ? max(1, $tipHeight - $blockHeight + 1)
                    : 1;
                $confirmedAt = $detectedAt;
            }

            $payments[] = [
                'txid' => $tx['txid'],
                'sats' => $received,
                'confirmed' => $confirmed,
                'confirmations' => $confirmations,
                'block_height' => $blockHeight,
                'detected_at' => $detectedAt,
                'confirmed_at' => $confirmedAt,
            ];
        }

        return $payments;
    }

    private function detectedAtFromStatus(array $status): Carbon
    {
        $timestamp = $status['block_time']
            ?? $status['received_time']
            ?? $status['time']
            ?? null;

        if ($timestamp) {
            return Carbon::createFromTimestamp($timestamp)->timezone(config('app.timezone'));
        }

        return Carbon::now();
    }

    private function satsReceivedForAddress(array $tx, string $address): int
    {
        $total = 0;
        foreach ($tx['vout'] ?? [] as $output) {
            if (($output['scriptpubkey_address'] ?? null) === $address) {
                $total += (int) ($output['value'] ?? 0);
            }
        }

        return $total;
    }
}
