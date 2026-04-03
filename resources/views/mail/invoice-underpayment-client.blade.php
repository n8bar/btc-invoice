@component('mail::message', ['invoice' => $invoice])
# Invoice {{ $invoice->number ?? $invoice->id }} still has a balance

We received a payment, but **${{ number_format($invoice->outstanding_usd ?? 0, 2) }}** remains outstanding on this invoice.

Please use the button below to view the invoice and send the remaining amount. If you believe this is in error, reply to this email.

@component('mail::button', ['url' => $invoice->public_url])
Pay remaining balance
@endcomponent

Thank you!
@endcomponent
