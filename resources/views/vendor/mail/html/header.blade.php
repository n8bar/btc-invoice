@props([
    'url',
    'brand' => 'CryptoZing',
    'tagline' => 'Watch-only bitcoin invoicing app',
])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link">
<img src="{{ asset('images/CZ.png') }}" class="brand-mark" alt="{{ $brand }} logo">
<span class="brand-name">{{ $brand }}</span>
<span class="brand-tagline">{{ $tagline }}</span>
</a>
</td>
</tr>
