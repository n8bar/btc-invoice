<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\InvoiceAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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

        if ($payment->isReattributed()) {
            return back()->with('error', 'Undo the reattribution before ignoring this payment.');
        }

        $request->merge([
            'ignore_reason' => trim((string) $request->input('ignore_reason', '')),
        ]);

        $validator = Validator::make($request->all(), [
            'ignore_reason' => ['required', 'string', 'max:500'],
            'correction_payment_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->redirectCorrectionValidationFailure(
                $invoice,
                $payment,
                $validator,
                'ignore_reason_'.$payment->id
            );
        }

        $data = $validator->validated();

        $statusBefore = $invoice->status;
        $previousAccountingInvoiceId = $payment->activeAccountingInvoiceId();

        DB::transaction(function () use ($data, $invoice, $payment, $request, $statusBefore, $previousAccountingInvoiceId) {
            $payment->forceFill([
                'ignored_at' => now(),
                'ignored_by_user_id' => $request->user()->id,
                'ignore_reason' => $data['ignore_reason'],
                'accounting_invoice_id' => null,
                'reattributed_at' => null,
                'reattributed_by_user_id' => null,
                'reattribute_reason' => null,
            ])->save();

            $refreshed = $this->refreshCorrectionTargets(
                $invoice,
                $previousAccountingInvoiceId && $previousAccountingInvoiceId !== $invoice->id
                    ? Invoice::query()->find($previousAccountingInvoiceId)
                    : null
            );

            Log::info('invoice.payment.ignored', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $statusBefore,
                'status_after' => $refreshed[$invoice->id]->status,
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
                'accounting_invoice_id' => $invoice->id,
                'reattributed_at' => null,
                'reattributed_by_user_id' => null,
                'reattribute_reason' => null,
            ])->save();

            $refreshed = $this->refreshCorrectionTargets($invoice);

            Log::info('invoice.payment.restored', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $statusBefore,
                'status_after' => $refreshed[$invoice->id]->status,
            ]);
        });

        return back()->with('status', 'Payment restored. Invoice totals refreshed.');
    }

    public function reattribute(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_if($payment->invoice_id !== $invoice->id, 404);

        if ($payment->is_adjustment) {
            return back()->with('error', 'Manual adjustments cannot be reattributed through payment corrections.');
        }

        if ($payment->isIgnored()) {
            return back()->with('error', 'Restore this payment before reattributing it.');
        }

        $request->merge([
            'reattribute_reason' => trim((string) $request->input('reattribute_reason', '')),
        ]);

        $validator = Validator::make($request->all(), [
            'destination_invoice_id' => [
                'required',
                'integer',
                Rule::exists('invoices', 'id')->where(function ($query) use ($invoice) {
                    $query->where('user_id', $invoice->user_id)
                        ->whereNull('deleted_at');
                }),
            ],
            'reattribute_reason' => ['required', 'string', 'max:500'],
            'correction_payment_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return $this->redirectCorrectionValidationFailure(
                $invoice,
                $payment,
                $validator,
                $validator->errors()->has('destination_invoice_id')
                    ? 'destination_invoice_id_'.$payment->id
                    : 'reattribute_reason_'.$payment->id
            );
        }

        $data = $validator->validated();

        $previousAccountingInvoiceId = $payment->activeAccountingInvoiceId();
        $destinationInvoiceId = (int) $data['destination_invoice_id'];

        if ($payment->isReattributed() && $destinationInvoiceId === $invoice->id) {
            return back()->with('error', 'Use Undo reattribution to return this payment to the source invoice.');
        }

        if ($previousAccountingInvoiceId === $destinationInvoiceId) {
            return back()->with('status', 'Payment already counts toward that invoice.');
        }

        $destination = Invoice::query()
            ->ownedBy($invoice->user_id)
            ->findOrFail($destinationInvoiceId);

        $sourceStatusBefore = $invoice->status;
        $destinationStatusBefore = $destination->status;

        DB::transaction(function () use (
            $data,
            $destination,
            $destinationInvoiceId,
            $invoice,
            $payment,
            $previousAccountingInvoiceId,
            $request,
            $sourceStatusBefore,
            $destinationStatusBefore
        ) {
            $payment->forceFill([
                'accounting_invoice_id' => $destinationInvoiceId,
                'reattributed_at' => now(),
                'reattributed_by_user_id' => $request->user()->id,
                'reattribute_reason' => $data['reattribute_reason'],
            ])->save();

            $refreshed = $this->refreshCorrectionTargets(
                $invoice,
                $destination,
                $previousAccountingInvoiceId
                    && !in_array($previousAccountingInvoiceId, [$invoice->id, $destination->id], true)
                    ? Invoice::query()->find($previousAccountingInvoiceId)
                    : null
            );

            Log::info('invoice.payment.reattributed', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $sourceStatusBefore,
                'status_after' => $refreshed[$invoice->id]->status,
                'source_invoice_id' => $invoice->id,
                'source_status_before' => $sourceStatusBefore,
                'source_status_after' => $refreshed[$invoice->id]->status,
                'destination_invoice_id' => $destination->id,
                'destination_status_before' => $destinationStatusBefore,
                'destination_status_after' => $refreshed[$destination->id]->status,
                'previous_accounting_invoice_id' => $previousAccountingInvoiceId,
                'reattribute_reason' => $data['reattribute_reason'],
                'shown_as_reattributed_out' => $payment->isReattributedOutFrom($invoice),
                'shown_as_reattributed_in' => $destination->id !== $invoice->id,
            ]);
        });

        return back()->with('status', 'Payment reattributed. Invoice totals refreshed.');
    }

    public function undoReattribution(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_if($payment->invoice_id !== $invoice->id, 404);

        if ($payment->is_adjustment) {
            return back()->with('error', 'Manual adjustments cannot be reattributed through payment corrections.');
        }

        if ($payment->isIgnored() || ! $payment->isReattributed()) {
            return back()->with('status', 'Payment already counts on the source invoice.');
        }

        $previousAccountingInvoiceId = $payment->activeAccountingInvoiceId();
        $destination = $previousAccountingInvoiceId && $previousAccountingInvoiceId !== $invoice->id
            ? Invoice::query()->find($previousAccountingInvoiceId)
            : null;

        $sourceStatusBefore = $invoice->status;
        $destinationStatusBefore = $destination?->status;
        $previousReattributeReason = $payment->reattribute_reason;

        DB::transaction(function () use (
            $destination,
            $invoice,
            $payment,
            $previousAccountingInvoiceId,
            $previousReattributeReason,
            $request,
            $sourceStatusBefore,
            $destinationStatusBefore
        ) {
            $payment->forceFill([
                'accounting_invoice_id' => $invoice->id,
                'reattributed_at' => null,
                'reattributed_by_user_id' => null,
                'reattribute_reason' => null,
            ])->save();

            $refreshed = $this->refreshCorrectionTargets($invoice, $destination);

            Log::info('invoice.payment.reattribution_undone', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
                'txid' => $payment->txid,
                'status_before' => $sourceStatusBefore,
                'status_after' => $refreshed[$invoice->id]->status,
                'source_invoice_id' => $invoice->id,
                'source_status_before' => $sourceStatusBefore,
                'source_status_after' => $refreshed[$invoice->id]->status,
                'destination_invoice_id' => $destination?->id,
                'destination_status_before' => $destinationStatusBefore,
                'destination_status_after' => $destination ? $refreshed[$destination->id]->status : null,
                'previous_accounting_invoice_id' => $previousAccountingInvoiceId,
                'previous_reattribute_reason' => $previousReattributeReason,
            ]);
        });

        return back()->with('status', 'Payment returned to the source invoice. Invoice totals refreshed.');
    }

    /**
     * @return array<int, Invoice>
     */
    private function refreshCorrectionTargets(?Invoice ...$invoices): array
    {
        $refreshed = [];

        foreach ($invoices as $invoice) {
            if (!$invoice) {
                continue;
            }

            if (array_key_exists($invoice->id, $refreshed)) {
                continue;
            }

            $invoice->refreshPaymentLedger();

            $freshInvoice = $invoice->fresh(['payments', 'client', 'user', 'deliveries']);
            if (!$freshInvoice) {
                continue;
            }

            $this->alerts->skipInvalidQueuedDeliveries($freshInvoice, 'Skipped after payment correction.');
            $this->alerts->checkPaymentThresholds($freshInvoice->fresh('payments'));

            $refreshed[$invoice->id] = $freshInvoice->fresh(['payments', 'client', 'user', 'deliveries']);
        }

        return $refreshed;
    }

    private function redirectCorrectionValidationFailure(
        Invoice $invoice,
        InvoicePayment $payment,
        ValidatorContract $validator,
        string $focusField
    ): RedirectResponse {
        $targetUrl = strtok(url()->previous(), '#') ?: route('invoices.show', $invoice);
        $rowId = 'payment-row-'.$payment->id;

        return redirect()->to($targetUrl.'#'.$rowId)
            ->withInput()
            ->withErrors($validator)
            ->with('correction_focus_field', $focusField)
            ->with('correction_focus_row', $rowId);
    }
}
