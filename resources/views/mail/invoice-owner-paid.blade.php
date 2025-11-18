@component('mail::message')
# Invoice {{ $invoice->number ?? $invoice->id }} paid

Good news — this invoice is now marked **paid**.

- **Client:** {{ $invoice->client->name ?? 'N/A' }}
- **Amount:** ${{ number_format($invoice->amount_usd ?? 0, 2) }} ({{ $invoice->amount_btc ?? '—' }} BTC)
- **Paid at:** {{ optional($invoice->paid_at)->toDayDateTimeString() ?? now()->toDayDateTimeString() }}

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

Thanks for using CryptoZing Invoice.
@endcomponent
