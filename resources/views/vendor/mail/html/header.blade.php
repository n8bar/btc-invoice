@props(['url'])
@php($brand = 'CryptoZing')
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link">
<img src="{{ asset('images/CZ.png') }}" class="brand-mark" alt="{{ $brand }} logo">
<span class="brand-name">{{ $brand }}</span>
<span class="brand-tagline">Watch-only BTC invoicing</span>
</a>
</td>
</tr>
