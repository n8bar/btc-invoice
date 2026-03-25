<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\BtcRate;
use App\Services\InvoiceAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvoicePaymentAdjustmentController extends Controller
{
    public function __construct(private readonly InvoiceAlertService $alerts)
    {
    }

    public function resolve(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $rateInfo = BtcRate::current();
        $rateUsd = $rateInfo['rate_usd'] ?? ($invoice->btc_rate ?: null);
        if (!$rateUsd || $rateUsd <= 0) {
            return back()->withErrors(['amount_usd' => 'Unable to determine BTC/USD rate. Refresh the rate and try again.']);
        }

        $summary = $invoice->paymentSummary($rateInfo);
        $outstandingUsd = $summary['outstanding_usd'] ?? null;
        $expectedUsd = $summary['expected_usd'] ?? null;

        if ($outstandingUsd === null || $outstandingUsd <= 0) {
            return back()->with('status', 'No outstanding balance to resolve.');
        }

        if ($expectedUsd === null || $expectedUsd <= 0) {
            return back()->withErrors(['amount_usd' => 'Unable to determine expected amount.']);
        }

        $threshold = $invoice->smallBalanceResolutionThresholdUsd($expectedUsd);

        if ($outstandingUsd > $threshold) {
            return back()->withErrors(['amount_usd' => 'Outstanding balance exceeds the small-balance resolution threshold.']);
        }

        $usdAmount = round($outstandingUsd, 2);
        $sats = (int) round(($usdAmount / $rateUsd) * Invoice::SATS_PER_BTC);
        if ($sats <= 0) {
            return back()->withErrors(['amount_usd' => 'Residual amount is too small to settle at the current rate.']);
        }

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'manual-resolve-' . \Illuminate\Support\Str::uuid(),
            'sats_received' => $sats,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => $rateUsd,
            'fiat_amount' => $usdAmount,
            'note' => 'Resolved small balance',
            'is_adjustment' => true,
        ]);

        $this->refreshAdjustmentTargets($invoice, 'Skipped after small-balance resolution.');

        return back()->with('status', 'Small balance resolved and invoice marked paid.');
    }

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $data = $request->validate([
            'amount_usd' => ['required', 'numeric', 'min:0.01'],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $rateInfo = BtcRate::current();
        $rateUsd = $rateInfo['rate_usd'] ?? ($invoice->btc_rate ?: null);

        if (!$rateUsd || $rateUsd <= 0) {
            return back()->withErrors(['amount_usd' => 'Unable to determine BTC/USD rate for adjustment. Try refreshing the invoice rate first.']);
        }

        $usdAmount = (float) $data['amount_usd'];
        $sats = (int) round(($usdAmount / $rateUsd) * Invoice::SATS_PER_BTC);

        if ($sats <= 0) {
            return back()->withErrors(['amount_usd' => 'Adjustment amount is too small for the current rate.']);
        }

        if ($data['direction'] === 'decrease') {
            $sats *= -1;
            $usdAmount *= -1;
        }

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'manual-' . Str::uuid(),
            'sats_received' => $sats,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => $rateUsd,
            'fiat_amount' => round($usdAmount, 2),
            'note' => $data['note'] ?? null,
            'is_adjustment' => true,
        ]);

        $this->refreshAdjustmentTargets($invoice, 'Skipped after manual adjustment.');

        return back()->with('status', 'Adjustment recorded.');
    }

    public function reverse(Request $request, Invoice $invoice, InvoicePayment $payment): RedirectResponse
    {
        $this->authorize('update', $invoice);
        abort_if($payment->invoice_id !== $invoice->id, 404);

        if (! $payment->is_adjustment) {
            return back()->with('error', 'Only manual adjustments can be reversed.');
        }

        $existingReversal = InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->where('is_adjustment', true)
            ->where('note', 'reversal of '.$payment->txid)
            ->exists();

        if ($existingReversal) {
            return back()->with('status', 'Adjustment already reversed.');
        }

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'txid' => 'manual-reversal-' . Str::uuid(),
            'sats_received' => $payment->sats_received * -1,
            'detected_at' => now(),
            'confirmed_at' => now(),
            'usd_rate' => $payment->usd_rate,
            'fiat_amount' => $payment->fiat_amount !== null
                ? round((float) $payment->fiat_amount * -1, 2)
                : null,
            'note' => 'reversal of '.$payment->txid,
            'is_adjustment' => true,
        ]);

        $this->refreshAdjustmentTargets($invoice, 'Skipped after manual adjustment reversal.');

        return back()->with('status', 'Adjustment reversed.');
    }

    private function refreshAdjustmentTargets(Invoice $invoice, string $skipReason): void
    {
        $invoice->refreshPaymentLedger();
        $invoice->refresh();
        $this->alerts->skipInvalidQueuedDeliveries($invoice, $skipReason);
        $this->alerts->checkPaymentThresholds($invoice);
    }
}
