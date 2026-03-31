@props([
    'brand' => 'CryptoZing',
    'footerBlurb' => 'CryptoZing is a watch-only bitcoin invoicing app and leaves final payment interpretation with the invoice issuer.',
])
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p>{{ $footerBlurb }}</p>
<p><a href="{{ config('app.url') }}">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a></p>
</td>
</tr>
</table>
</td>
</tr>
