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
            'usd_rate' => $rateUsd,
            'fiat_amount' => round($usdAmount, 2),
            'note' => $data['note'] ?? null,
            'is_adjustment' => true,
        ]);

        $invoice->refreshPaymentState();
        $invoice->refresh();
        $this->alerts->checkPaymentThresholds($invoice);

        return back()->with('status', 'Adjustment recorded.');
    }
}
