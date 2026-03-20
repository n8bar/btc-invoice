<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoicePaymentCorrectionController extends Controller
{
    public function __construct(private readonly InvoiceAlertService $alerts)
    {
    }

    public function ignore(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_if($payment->invoice_id !== $invoice->id, 404);

        if ($payment->is_adjustment) {
            return back()->with('error', 'Manual adjustments cannot be ignored.');
        }

        if ($payment->isIgnored()) {
            return back()->with('status', 'Payment already ignored.');
        }

        $request->merge([
            'ignore_reason' => trim((string) $request->input('ignore_reason', '')),
        ]);

        $data = $request->validate([
            'ignore_reason' => ['required', 'string', 'max:500'],
            'correction_payment_id' => ['nullable', 'integer'],
        ]);

        $statusBefore = $invoice->status;

        DB::transaction(function () use ($data, $invoice, $payment, $request, $statusBefore) {
            $payment->forceFill([
                'ignored_at' => now(),
                'ignored_by_user_id' => $request->user()->id,
                'ignore_reason' => $data['ignore_reason'],
            ])->save();

            $invoice->refreshPaymentLedger();
            $invoice->refresh();
            $invoice->load(['payments', 'client', 'user']);

            $this->alerts->skipInvalidQueuedDeliveries($invoice, 'Skipped after payment correction.');
            $this->alerts->checkPaymentThresholds($invoice);

            Log::info('invoice.payment.ignored', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $statusBefore,
                'status_after' => $invoice->status,
                'ignore_reason' => $payment->ignore_reason,
            ]);
        });

        return back()->with('status', 'Payment ignored. Invoice totals refreshed.');
    }

    public function restore(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_if($payment->invoice_id !== $invoice->id, 404);

        if ($payment->is_adjustment) {
            return back()->with('error', 'Manual adjustments cannot be restored through payment corrections.');
        }

        if (! $payment->isIgnored()) {
            return back()->with('status', 'Payment is already active.');
        }

        $statusBefore = $invoice->status;

        DB::transaction(function () use ($invoice, $payment, $request, $statusBefore) {
            $payment->forceFill([
                'ignored_at' => null,
                'ignored_by_user_id' => null,
                'ignore_reason' => null,
            ])->save();

            $invoice->refreshPaymentLedger();
            $invoice->refresh();
            $invoice->load(['payments', 'client', 'user']);

            $this->alerts->skipInvalidQueuedDeliveries($invoice, 'Skipped after payment correction.');
            $this->alerts->checkPaymentThresholds($invoice);

            Log::info('invoice.payment.restored', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $statusBefore,
                'status_after' => $invoice->status,
            ]);
        });

        return back()->with('status', 'Payment restored. Invoice totals refreshed.');
    }
}
