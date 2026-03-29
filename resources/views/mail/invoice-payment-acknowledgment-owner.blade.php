@component('mail::message')
# Review detected payment for Invoice {{ $invoice->number ?? $invoice->id }}

A Bitcoin payment of **{{ $invoice->formatBitcoinAmount(($payment?->sats_received ?? 0) / \App\Models\Invoice::SATS_PER_BTC) ?? '0' }} BTC** was detected for this invoice.

- **Client:** {{ $invoice->client->name ?? 'N/A' }}
- **TXID:** {{ $payment?->txid ?? $delivery->context_key ?? 'N/A' }}

Review the payment rows and send any higher-certainty follow-up only after the invoice payment history looks correct.

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

— CryptoZing Invoice
@endcomponent
