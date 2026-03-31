@props([
    'brand' => 'CryptoZing',
    'tagline' => 'Watch-only bitcoin invoicing app',
    'footerBlurb' => 'CryptoZing is a watch-only bitcoin invoicing app and leaves final payment interpretation with the invoice issuer.',
])
{{ $brand }}
{{ $tagline }}

{{ $footerBlurb }}
{{ preg_replace('#^https?://#', '', config('app.url')) }}
