<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardSnapshot
{
    public const CACHE_SECONDS = 60;

    public function forUser(User $user): array
    {
        $cacheKey = "dashboard:snapshot:user:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_SECONDS, function () use ($user) {
            return [
                'counts' => $this->counts($user),
                'totals' => $this->totals($user),
                'recent_payments' => $this->recentPayments($user),
            ];
        });
    }

    private function counts(User $user): array
    {
        $today = Carbon::today(config('app.timezone'));
        $windowStart = $today->copy()->subDays(7);
        $windowEnd = $today->copy()->addDay();

        $open = Invoice::query()->ownedBy($user)->open()->count();
        $pastDue = Invoice::query()->ownedBy($user)->pastDue($today)->count();
        $upcomingDue = Invoice::query()->ownedBy($user)->dueSoon($today)->count();

        $paymentsLast7d = InvoicePayment::query()
            ->forUserInvoices($user)
            ->recentBetween($windowStart, $windowEnd)
            ->count();

        $draft = Invoice::query()->ownedBy($user)->where('status', 'draft')->count();
        $sent = Invoice::query()->ownedBy($user)->where('status', 'sent')->count();
        $partial = Invoice::query()->ownedBy($user)->where('status', 'partial')->count();

        return [
            'open' => $open,
            'past_due' => $pastDue,
            'upcoming_due' => $upcomingDue,
            'payments_last_7d' => $paymentsLast7d,
            'draft' => $draft,
            'sent' => $sent,
            'partial' => $partial,
        ];
    }

    private function totals(User $user): array
    {
        $invoices = Invoice::query()
            ->with(['payments' => function ($query) {
                $query->select(
                    'id',
                    'invoice_id',
                    'sats_received',
                    'usd_rate',
                    'fiat_amount',
                    'detected_at',
                    'confirmed_at',
                    'is_adjustment',
                    'created_at'
                );
            }])
            ->ownedBy($user)
            ->whereIn('status', ['sent', 'partial', 'draft'])
            ->get([
                'id', 'user_id', 'status', 'amount_usd', 'btc_rate', 'amount_btc',
                'due_date',
            ]);

        $outstandingUsd = 0.0;
        $outstandingSats = 0;
        $pastDueUsd = 0.0;
        $upcomingDueUsd = 0.0;
        $today = Carbon::today(config('app.timezone'));
        $paymentsLast7dUsd = 0.0;

        foreach ($invoices as $invoice) {
            $paidUsd = $invoice->sumPaymentsUsd(true);
            $expectedUsd = $invoice->amount_usd !== null ? (float) $invoice->amount_usd : 0.0;
            $remaining = max($expectedUsd - $paidUsd, 0.0);
            $outstandingUsd += $remaining;

            if ($invoice->due_date && in_array($invoice->status, ['sent', 'partial'], true)) {
                if ($invoice->due_date->lt($today)) {
                    $pastDueUsd += $remaining;
                } elseif ($invoice->due_date->betweenIncluded($today, $today->copy()->addDays(7))) {
                    $upcomingDueUsd += $remaining;
                }
            }

            $expectedSats = $invoice->expectedPaymentSats();
            if ($expectedSats !== null) {
                $outstandingSats += max($expectedSats - $invoice->sumPaymentSats(true), 0);
            }
        }

        $paymentsLast7dUsd = InvoicePayment::query()
            ->forUserInvoices($user)
            ->recentBetween($today->copy()->subDays(7), $today->copy()->addDay())
            ->where(function ($query) {
                $query->whereNotNull('invoice_payments.confirmed_at')
                    ->orWhere('invoice_payments.is_adjustment', true);
            })
            ->with('invoice:id,user_id,btc_rate')
            ->get()
            ->sum(function (InvoicePayment $payment) {
                $invoice = $payment->invoice;
                if (!$invoice) {
                    return 0.0;
                }

                return $this->paymentUsdValue($invoice, $payment);
            });

        $outstandingBtc = $outstandingSats > 0
            ? round($outstandingSats / Invoice::SATS_PER_BTC, 8)
            : 0.0;

        return [
            'outstanding_usd' => round($outstandingUsd, 2),
            'outstanding_btc' => $outstandingBtc,
            'past_due_usd' => round($pastDueUsd, 2),
            'upcoming_due_usd' => round($upcomingDueUsd, 2),
            'payments_last_7d_usd' => round($paymentsLast7dUsd, 2),
        ];
    }

    private function recentPayments(User $user): array
    {
        $payments = InvoicePayment::query()
            ->select(['invoice_payments.*'])
            ->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
            ->where('invoices.user_id', $user->id)
            ->whereNull('invoices.deleted_at')
            ->orderByRaw('COALESCE(invoice_payments.detected_at, invoice_payments.created_at) DESC')
            ->orderByDesc('invoice_payments.id')
            ->limit(5)
            ->with(['invoice' => function ($query) {
                $query->select('id', 'user_id', 'client_id', 'number', 'status', 'amount_usd', 'btc_rate');
            }, 'invoice.client:id,name'])
            ->get();

        return $payments->map(function (InvoicePayment $payment) {
            $invoice = $payment->invoice;
            $detectedAt = $payment->detected_at ?? $payment->created_at;
            $amountUsd = $this->paymentUsdValue($invoice, $payment);
            $outstandingSats = $invoice?->outstanding_sats;
            $isPartial = $invoice && $outstandingSats !== null && $outstandingSats > 0;

            return [
                'invoice_id' => $invoice?->id,
                'invoice_number' => $invoice?->number,
                'client_name' => $invoice?->client?->name,
                'status' => $invoice?->status,
                'amount_usd' => $amountUsd,
                'detected_at' => $detectedAt,
                'is_partial' => $isPartial,
            ];
        })->all();
    }

    private function paymentUsdValue(Invoice $invoice, InvoicePayment $payment): float
    {
        if ($payment->fiat_amount !== null) {
            return (float) $payment->fiat_amount;
        }

        if ($payment->usd_rate !== null) {
            return round(($payment->sats_received / Invoice::SATS_PER_BTC) * (float) $payment->usd_rate, 2);
        }

        if ($invoice->btc_rate) {
            return round(($payment->sats_received / Invoice::SATS_PER_BTC) * (float) $invoice->btc_rate, 2);
        }

        return 0.0;
    }
}
