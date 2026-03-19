<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\WalletSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UnsupportedConfigurationEvidenceService
{
    public function __construct(
        private readonly WalletKeyLineage $lineage
    ) {
    }

    /**
     * @param  array<int, array{txid: string, sats: int}>  $payments
     */
    public function flagPaymentCollisionEvidence(Invoice $invoice, array $payments): void
    {
        if (! $invoice->payment_address || ! $invoice->wallet_network || $payments === []) {
            return;
        }

        $implicatedInvoices = Invoice::query()
            ->where('payment_address', $invoice->payment_address)
            ->where('wallet_network', $invoice->wallet_network)
            ->get([
                'id',
                'user_id',
                'number',
                'payment_address',
                'wallet_key_fingerprint',
                'wallet_network',
                'unsupported_configuration_flagged',
            ]);

        if ($implicatedInvoices->count() <= 1) {
            return;
        }

        $flaggedAt = now();
        $txid = (string) (collect($payments)->pluck('txid')->filter()->first() ?? 'unknown');
        $invoiceReferences = $implicatedInvoices
            ->mapWithKeys(fn (Invoice $candidate) => [$candidate->id => $this->invoiceReference($candidate)]);

        foreach ($implicatedInvoices as $implicatedInvoice) {
            $otherInvoiceReferences = $invoiceReferences
                ->except($implicatedInvoice->id)
                ->values();

            $details = $this->detailsForInvoice(
                $implicatedInvoice,
                $otherInvoiceReferences,
                $txid,
                (string) $invoice->payment_address,
            );

            $implicatedInvoice->markUnsupportedConfiguration(
                source: 'evidence',
                reason: 'payment_collision',
                details: $details,
                flaggedAt: $flaggedAt,
            );

            $this->flagCurrentWalletIfLineageMatches($implicatedInvoice, $details, $flaggedAt);
        }

        Log::warning('invoice.unsupported_configuration.payment_collision', [
            'invoice_ids' => $implicatedInvoices->pluck('id')->all(),
            'address' => $invoice->payment_address,
            'network' => $invoice->wallet_network,
            'txid' => $txid,
        ]);
    }

    /**
     * @param  Collection<int, string>  $otherInvoiceReferences
     */
    private function detailsForInvoice(
        Invoice $invoice,
        Collection $otherInvoiceReferences,
        string $txid,
        string $address
    ): string {
        $references = $otherInvoiceReferences->implode(', ');
        $currentReference = $this->invoiceReference($invoice);

        return "Detected payment tx {$txid} on address {$address} for {$currentReference}, which is also assigned to {$references}.";
    }

    private function flagCurrentWalletIfLineageMatches(Invoice $invoice, string $details, Carbon $flaggedAt): void
    {
        if (! $invoice->wallet_key_fingerprint || ! $invoice->wallet_network) {
            return;
        }

        $wallet = WalletSetting::query()
            ->where('user_id', $invoice->user_id)
            ->first();

        if (! $wallet?->bip84_xpub) {
            return;
        }

        $currentNetwork = $this->lineage->normalizeNetwork((string) $wallet->network);
        $currentFingerprint = $this->lineage->fingerprint($currentNetwork, (string) $wallet->bip84_xpub);

        if ($currentNetwork !== $invoice->wallet_network || $currentFingerprint !== $invoice->wallet_key_fingerprint) {
            return;
        }

        $wallet->markUnsupportedConfiguration(
            source: 'evidence',
            reason: 'payment_collision',
            details: $details,
            flaggedAt: $flaggedAt,
        );
    }

    private function invoiceReference(Invoice $invoice): string
    {
        return $invoice->number
            ? "invoice {$invoice->number} (#{$invoice->id})"
            : "invoice #{$invoice->id}";
    }
}
