@component('mail::message', ['invoice' => $invoice])
# Review detected payment for Invoice {{ $invoice->number ?? $invoice->id }}

A Bitcoin payment of **{{ $invoice->formatBitcoinAmount(($payment?->sats_received ?? 0) / \App\Models\Invoice::SATS_PER_BTC) ?? '0' }} BTC** was detected for this invoice.

- **Client:** {{ $invoice->client->name ?? 'N/A' }}
- **TXID:** {{ $payment?->txid ?? $delivery->context_key ?? 'N/A' }}

Review the payment history on the invoice page.@if($invoice->outstanding_usd > 0) You may want to follow up with the client on the remaining balance of ${{ number_format($invoice->outstanding_usd, 2) }}.@endif

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

Thanks for using CryptoZing
@endcomponent
