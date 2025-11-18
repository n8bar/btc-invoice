@component('mail::message')
# Invoice {{ $invoice->number ?? $invoice->id }} is past due

This invoice is now past its due date and still has an outstanding balance.

- **Client:** {{ $invoice->client->name ?? 'N/A' }}
- **Due date:** {{ optional($invoice->due_date)->toDateString() ?? '—' }}
- **Outstanding:** ${{ number_format($invoice->amount_usd - ($invoice->payments->sum('fiat_amount') ?? 0), 2) }} (approx.)

Consider nudging the client or recording a manual adjustment if you’ve already reconciled it.

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Open invoice
@endcomponent

— CryptoZing Invoice
@endcomponent
