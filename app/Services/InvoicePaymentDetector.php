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
     * @return array{
     *     txid: string,
     *     sats: int,
     *     confirmed: bool,
     *     confirmations: int,
     *     block_height: int|null,
     *     detected_at: \Illuminate\Support\Carbon,
     *     confirmed_at: \Illuminate\Support\Carbon|null
     * }|null
     */
    public function detect(Invoice $invoice, string $network): ?array
    {
        $address = $invoice->payment_address;
        if (!$address) {
            return null;
        }

        $expected = $invoice->expectedPaymentSats();
        if ($expected === null) {
            return null;
        }

        $transactions = $this->mempoolClient->transactions($network, $address);
        if (empty($transactions)) {
            return null;
        }

        $tolerance = 5; // satoshis leeway for rounding differences
        foreach ($transactions as $tx) {
            $received = $this->satsReceivedForAddress($tx, $address);
            if ($received <= 0 || $received + $tolerance < $expected) {
                continue;
            }

            $status = $tx['status'] ?? [];
            $confirmed = (bool) ($status['confirmed'] ?? false);
            $blockHeight = $status['block_height'] ?? null;
            $detectedAt = Carbon::now();
            $confirmedAt = null;

            if (!empty($status['block_time'])) {
                $detectedAt = Carbon::createFromTimestamp($status['block_time'])->timezone(config('app.timezone'));
            }

            $confirmations = 0;
            if ($confirmed && $blockHeight) {
                $tip = $this->mempoolClient->tipHeight($network);
                $confirmations = $tip && $tip >= $blockHeight ? max(1, $tip - $blockHeight + 1) : 1;
                $confirmedAt = $detectedAt;
            }

            return [
                'txid' => $tx['txid'],
                'sats' => $received,
                'confirmed' => $confirmed,
                'confirmations' => $confirmations,
                'block_height' => $blockHeight,
                'detected_at' => $detectedAt,
                'confirmed_at' => $confirmedAt,
            ];
        }

        return null;
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
