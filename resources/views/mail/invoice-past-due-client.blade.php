@component('mail::message')
# Invoice {{ $invoice->number ?? $invoice->id }} is past due

Our records show an outstanding balance of approximately **${{ number_format($invoice->amount_usd - ($invoice->payments->sum('fiat_amount') ?? 0), 2) }} USD**.

Please review the invoice and settle the remaining amount. If you already paid, just reply to this email so we can reconcile it.

@component('mail::button', ['url' => $invoice->public_url])
View invoice
@endcomponent

Thank you!
@endcomponent
