@props([
    'brand' => 'CryptoZing',
    'tagline' => 'Non-custodial bitcoin invoicing',
    'footerBlurb' => 'Create bitcoin invoices, monitor incoming payments, and send receipts with CryptoZing.',
])
{{ $brand }}
{{ $tagline }}

{{ $footerBlurb }}
{{ preg_replace('#^https?://#', '', config('app.url')) }}
