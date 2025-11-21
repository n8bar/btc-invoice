@php
    $paidFiat = $invoice->payments->sum('fiat_amount') ?? 0;
    $outstandingUsd = max(($invoice->amount_usd ?? 0) - $paidFiat, 0);
    $outstandingBtc = max(($invoice->amount_btc ?? 0) - (($invoice->payment_amount_sat ?? 0) / \App\Models\Invoice::SATS_PER_BTC), 0);
@endphp

@component('mail::message')
# Quick reminder for Invoice {{ $invoice->number ?? $invoice->id }}

We noticed more than one payment attempt for this invoice. Splitting a single invoice across multiple Bitcoin transactions usually means **paying extra miner fees** and might slow down processing.

@component('mail::panel')
**Outstanding balance:** ${{ number_format($outstandingUsd, 2) }} USD  
**BTC equivalent:** {{ number_format($outstandingBtc, 8) }} BTC  
**Due date:** {{ optional($invoice->due_date)->toFormattedDateString() ?? '—' }}
@endcomponent

@component('mail::button', ['url' => $invoice->public_url])
Open invoice
@endcomponent

If you intentionally split the payment or have questions, just reply to this email and we’ll help square it up.

Thank you!
@endcomponent
