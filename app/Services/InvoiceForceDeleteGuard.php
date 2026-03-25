<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Collection;

class InvoiceForceDeleteGuard
{
    /**
     * @return array<int, array{type: string, message: string}>
     */
    public function blockers(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'sourcePayments' => fn ($query) => $query
                ->with(['accountingInvoice' => fn ($invoiceQuery) => $invoiceQuery->withTrashed()->select('id', 'number', 'deleted_at')]),
            'payments' => fn ($query) => $query
                ->whereColumn('invoice_payments.invoice_id', '!=', 'invoice_payments.accounting_invoice_id')
                ->with(['sourceInvoice' => fn ($invoiceQuery) => $invoiceQuery->withTrashed()->select('id', 'number', 'deleted_at')]),
        ]);

        $sourcePayments = $invoice->sourcePayments;
        $incomingReattributions = $invoice->payments
            ->filter(fn (InvoicePayment $payment) => $payment->isReattributedInto($invoice))
            ->values();

        $blockers = [];

        $detectedRows = $sourcePayments->filter(
            fn (InvoicePayment $payment) => ! $payment->is_adjustment
                && ! $payment->isIgnored()
                && ! $payment->isReattributedOutFrom($invoice)
        );
        if ($detectedRows->isNotEmpty()) {
            $blockers[] = [
                'type' => 'detected_payment',
                'message' => $this->countLabel($detectedRows->count(), 'detected payment row')
                    . ' still belong to this invoice. Purge those retained payment rows before permanently deleting the invoice.',
            ];
        }

        $ignoredRows = $sourcePayments->filter(
            fn (InvoicePayment $payment) => ! $payment->is_adjustment && $payment->isIgnored()
        );
        if ($ignoredRows->isNotEmpty()) {
            $blockers[] = [
                'type' => 'ignored_payment',
                'message' => $this->countLabel($ignoredRows->count(), 'ignored payment row')
                    . ' still belong to this invoice. Ignore does not clear the hard-delete blocker.',
            ];
        }

        $manualAdjustments = $sourcePayments->where('is_adjustment', true)->values();
        if ($manualAdjustments->isNotEmpty()) {
            $blockers[] = [
                'type' => 'manual_adjustment',
                'message' => $this->countLabel($manualAdjustments->count(), 'manual adjustment row')
                    . ' still belong to this invoice. Purge those retained adjustments before permanently deleting the invoice.',
            ];
        }

        $outgoingReattributions = $sourcePayments->filter(
            fn (InvoicePayment $payment) => ! $payment->is_adjustment && $payment->isReattributedOutFrom($invoice)
        );
        if ($outgoingReattributions->isNotEmpty()) {
            $blockers[] = [
                'type' => 'outgoing_reattribution',
                'message' => $this->countLabel($outgoingReattributions->count(), 'outgoing reattribution')
                    . ' still originate from this invoice and count elsewhere'
                    . $this->relatedInvoiceSuffix($outgoingReattributions, 'accountingInvoice')
                    . '. Restore this invoice and resolve those source payment rows before permanently deleting it.',
            ];
        }

        if ($incomingReattributions->isNotEmpty()) {
            $blockers[] = [
                'type' => 'incoming_reattribution',
                'message' => $this->countLabel($incomingReattributions->count(), 'incoming reattribution')
                    . ' still count on this invoice'
                    . $this->relatedInvoiceSuffix($incomingReattributions, 'sourceInvoice')
                    . '. Resolve them on the source invoice first before permanently deleting this invoice.',
            ];
        }

        return $blockers;
    }

    private function countLabel(int $count, string $label): string
    {
        return $count . ' ' . $label . ($count === 1 ? '' : 's');
    }

    private function relatedInvoiceSuffix(Collection $payments, string $relation): string
    {
        $numbers = $payments
            ->map(fn (InvoicePayment $payment) => $payment->{$relation}?->number)
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return '';
        }

        $list = $numbers->take(3)->implode(', ');
        if ($numbers->count() > 3) {
            $list .= ', and others';
        }

        return " ({$list})";
    }
}
