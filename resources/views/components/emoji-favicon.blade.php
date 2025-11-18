@props([
    'symbol' => '📄',
    'bg' => null,
])

@push('page-favicon')
    @php
        $rect = $bg
            ? '<rect width="64" height="64" rx="12" fill="' . $bg . '"/>'
            : '';
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  {$rect}
  <text x="50%" y="60%" font-size="36" text-anchor="middle">{$symbol}</text>
</svg>
SVG;
    @endphp
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ rawurlencode($svg) }}">
@endpush
