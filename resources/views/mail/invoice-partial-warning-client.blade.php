@php
    $summary = $invoice->paymentSummary(\App\Services\BtcRate::current());
    $outstandingUsd = $summary['outstanding_usd'] ?? 0;
    $outstandingBtc = $summary['outstanding_btc_formatted'] ?? null;
@endphp

@component('mail::message')
# Quick reminder for Invoice {{ $invoice->number ?? $invoice->id }}

We noticed more than one payment attempt for this invoice. Splitting a single invoice across multiple Bitcoin transactions usually means **paying extra miner fees** and might slow down processing.

@component('mail::panel')
**Outstanding balance:** ${{ number_format($outstandingUsd, 2) }} USD  
**BTC equivalent:** {{ $outstandingBtc ?? '—' }} BTC  
**Due date:** {{ optional($invoice->due_date)->toFormattedDateString() ?? '—' }}
@endcomponent

@component('mail::button', ['url' => $invoice->public_url])
Open invoice
@endcomponent

If you intentionally split the payment or have questions, just reply to this email and we’ll help square it up.

Thank you!
@endcomponent
