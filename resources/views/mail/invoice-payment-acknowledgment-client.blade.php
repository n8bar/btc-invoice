@component('mail::message', ['invoice' => $invoice])
# Bitcoin payment detected

Hi {{ $client->name ?? 'there' }},

A Bitcoin payment of **{{ $invoice->formatBitcoinAmount(($payment?->sats_received ?? 0) / \App\Models\Invoice::SATS_PER_BTC) ?? '0' }} BTC** was detected.

No action is needed right now.

The invoice issuer has been notified to review it promptly.

@if ($publicUrl)
@component('mail::button', ['url' => $publicUrl])
View invoice
@endcomponent
@endif

Thanks,<br>
{{ $invoice->user->name ?? config('app.name') }}
@endcomponent
