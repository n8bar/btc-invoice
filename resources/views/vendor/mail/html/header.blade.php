@props([
    'url',
    'brand' => 'CryptoZing',
    'tagline' => 'Non-custodial bitcoin invoicing',
    'showLogo' => true,
])
@php
    $logoSrc = null;

    if ($showLogo) {
        $logoPath = public_path('images/CZ.png');

        if (is_file($logoPath)) {
            if (isset($message) && method_exists($message, 'embed')) {
                $logoSrc = $message->embed($logoPath);
            } else {
                $logoContents = file_get_contents($logoPath);

                if ($logoContents !== false) {
                    $logoSrc = 'data:image/png;base64,'.base64_encode($logoContents);
                }
            }
        }
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" class="brand-link">
@if ($logoSrc)
<img src="{{ $logoSrc }}" class="brand-mark" alt="{{ $brand }} logo">
@endif
<span class="brand-name">{{ $brand }}</span>
<span class="brand-tagline">{{ $tagline }}</span>
</a>
</td>
</tr>
