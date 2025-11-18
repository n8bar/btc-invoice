@component('mail::message')
# Invoice {{ $invoice->number ?? $invoice->id }} still has a balance

We received a payment, but about **{{ number_format($invoice->underpaymentPercent() ?? 0, 1) }}%** remains outstanding.

Please use the button below to view the invoice and send the remaining amount. If you believe this is in error, reply to this email.

@component('mail::button', ['url' => $invoice->public_url])
Pay remaining balance
@endcomponent

Thank you!
@endcomponent
