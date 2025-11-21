@php
    $paidFiat = $invoice->payments->sum('fiat_amount') ?? 0;
    $outstandingUsd = max(($invoice->amount_usd ?? 0) - $paidFiat, 0);
    $outstandingBtc = max(($invoice->amount_btc ?? 0) - (($invoice->payment_amount_sat ?? 0) / \App\Models\Invoice::SATS_PER_BTC), 0);
@endphp

@component('mail::message')
# Heads up: Invoice {{ $invoice->number ?? $invoice->id }} received multiple payments

We just emailed the client reminding them that splitting payments adds miner fees and can slow settlement. This usually happens when they try to “top up” the invoice across several transactions.

@component('mail::panel')
**Outstanding balance:** ${{ number_format($outstandingUsd, 2) }} USD  
**BTC equivalent:** {{ number_format($outstandingBtc, 8) }} BTC  
**Client:** {{ $invoice->client->name ?? 'N/A' }}
@endcomponent

@component('mail::button', ['url' => route('invoices.show', $invoice)])
View invoice
@endcomponent

No action is required unless you’d like to follow up with the client or confirm whether the split payment was intentional.

Thanks!
@endcomponent
