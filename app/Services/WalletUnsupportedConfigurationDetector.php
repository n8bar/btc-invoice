<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WalletSetting;
use App\Services\Blockchain\MempoolClient;

class WalletUnsupportedConfigurationDetector
{
    public function __construct(
        private readonly HdWallet $wallet,
        private readonly MempoolClient $mempoolClient,
        private readonly WalletKeyLineage $lineage,
    ) {
    }

    /**
     * @return array{
     *   source: string,
     *   reason: string,
     *   details: string,
     *   address: string,
     *   derivation_index: int,
     *   txid: string
     * }|null
     */
    public function detectProactiveOutsideReceiveActivity(WalletSetting $wallet): ?array
    {
        $network = $this->lineage->normalizeNetwork((string) $wallet->network);
        $xpub = (string) $wallet->bip84_xpub;
        $fingerprint = $this->lineage->fingerprint($network, $xpub);
        $scanLimit = $this->proactiveScanLimit($wallet, $fingerprint, $network);

        if ($scanLimit <= 0) {
            return null;
        }

        $derivedAddresses = [];
        for ($index = 0; $index < $scanLimit; $index++) {
            $derivedAddresses[$index] = $this->wallet->deriveAddress($xpub, $index, $network);
        }

        $knownInvoiceAddresses = Invoice::query()
            ->where('user_id', $wallet->user_id)
            ->where('wallet_network', $network)
            ->where('wallet_key_fingerprint', $fingerprint)
            ->whereNotNull('payment_address')
            ->pluck('id', 'payment_address')
            ->all();

        $transactionsByAddress = $this->mempoolClient->transactionsForAddresses($network, array_values($derivedAddresses));

        foreach ($derivedAddresses as $index => $address) {
            if (array_key_exists($address, $knownInvoiceAddresses)) {
                continue;
            }

            foreach ($transactionsByAddress[$address] ?? [] as $tx) {
                $received = $this->satsReceivedForAddress($tx, $address);
                if ($received <= 0) {
                    continue;
                }

                $txid = (string) ($tx['txid'] ?? 'unknown');

                return [
                    'source' => 'proactive',
                    'reason' => 'outside_receive_activity',
                    'details' => "Detected prior receive activity on derived address {$address} at index {$index} (tx {$txid}).",
                    'address' => $address,
                    'derivation_index' => $index,
                    'txid' => $txid,
                ];
            }
        }

        return null;
    }

    private function proactiveScanLimit(WalletSetting $wallet, string $fingerprint, string $network): int
    {
        $window = (int) config('blockchain.unsupported_wallet_detection.proactive_address_scan_count', 10);
        $cap = (int) config('blockchain.unsupported_wallet_detection.proactive_address_scan_cap', 25);

        if ($window <= 0 || $cap <= 0) {
            return 0;
        }

        $window = max($window, 1);
        $cap = max($cap, $window);

        $highestAssigned = Invoice::query()
            ->where('user_id', $wallet->user_id)
            ->where('wallet_network', $network)
            ->where('wallet_key_fingerprint', $fingerprint)
            ->whereNotNull('derivation_index')
            ->max('derivation_index');

        $knownSpan = $highestAssigned === null ? 0 : ((int) $highestAssigned + 1);

        return min($cap, max($window, $knownSpan + $window));
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
