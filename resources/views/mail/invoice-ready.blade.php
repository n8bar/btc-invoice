<x-mail::message :invoice="$invoice">
# Invoice {{ $invoice->number ?? $invoice->id }} is ready

Hi {{ $client->name ?? 'there' }},

Your invoice for **${{ number_format($invoice->amount_usd, 2) }}** is ready. Follow the link below to review the live share view with QR code and payment instructions.

@if (!empty($delivery->message))
> {{ $delivery->message }}
@endif

<x-mail::panel>
**Due date:** {{ optional($invoice->due_date)->toFormattedDateString() ?? '—' }}  
**Amount (BTC):** {{ $invoice->amount_btc ?? '—' }}  
**Status:** Open
</x-mail::panel>

<x-mail::panel>
**Tip:** Send the full balance in a single Bitcoin transaction if possible. If you need to split between wallets, multiple payments are accepted, but this can add extra miner fees and slow processing.
</x-mail::panel>

@if ($publicUrl)
<x-mail::button :url="$publicUrl">
View Invoice
</x-mail::button>
@endif

Thanks,<br>
{{ $invoice->user->name ?? config('app.name') }}
</x-mail::message>
