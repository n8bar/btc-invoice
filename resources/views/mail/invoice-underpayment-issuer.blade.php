@component('mail::message', ['invoice' => $invoice])
# Underpayment alert for invoice {{ $invoice->number ?? $invoice->id }}

The latest payment left **${{ number_format($invoice->outstanding_usd, 2) }}** still outstanding.

Consider following up with the client or recording a manual adjustment if you've already reconciled it elsewhere.

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

Thanks for using CryptoZing
@endcomponent
