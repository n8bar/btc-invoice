<x-mail::message>
# Receipt for Invoice {{ $invoice->number ?? $invoice->id }}

Hi {{ $client->name ?? 'there' }},

Thanks for your payment. We detected funds for **${{ number_format($invoice->amount_usd, 2) }}** on {{ optional($invoice->payment_detected_at)->toDayDateTimeString() ?? 'N/A' }}.

<x-mail::panel>
**Amount received:** {{ $invoice->payment_amount_formatted ?? '—' }} BTC  
**USD total:** ${{ number_format((float) $invoice->amount_usd, 2) }}  
**TXID:** {{ $invoice->txid ?? '—' }}  
**Confirmations:** {{ $invoice->payment_confirmations ?? '0' }}
</x-mail::panel>

@if ($publicUrl)
<x-mail::button :url="$publicUrl">
View Paid Invoice
</x-mail::button>
@endif

Thanks,<br>
{{ $invoice->user->name ?? config('app.name') }}
</x-mail::message>
