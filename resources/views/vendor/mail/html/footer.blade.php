@php($brand = 'CryptoZing')
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p>{{ $brand }} keeps bitcoin watch-only and leaves final payment interpretation with the invoice issuer.</p>
<p><a href="{{ config('app.url') }}">{{ preg_replace('#^https?://#', '', config('app.url')) }}</a></p>
</td>
</tr>
</table>
</td>
</tr>
