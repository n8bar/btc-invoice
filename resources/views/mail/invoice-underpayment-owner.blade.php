@component('mail::message')
# Underpayment alert for invoice {{ $invoice->number ?? $invoice->id }}

The latest payment left roughly **{{ number_format($invoice->underpaymentPercent() ?? 0, 1) }}%** unpaid.

Consider following up with the client or recording a manual adjustment if you’ve already reconciled it elsewhere.

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

— CryptoZing Invoice
@endcomponent
