@component('mail::message')
# Overpayment flagged on invoice {{ $invoice->number ?? $invoice->id }}

The latest payment puts this invoice roughly **{{ number_format($invoice->overpaymentPercent() ?? 0, 1) }}%** above the total.

Decide whether to keep it as a tip, credit the client, or record a manual adjustment/refund.

@component('mail::button', ['url' => route('invoices.show', $invoice)])
Review invoice
@endcomponent

— CryptoZing Invoice
@endcomponent
