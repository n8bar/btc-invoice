@php
    $mailBrandInvoice = $invoice ?? null;
    $mailBrandUser = $mailBrandInvoice?->user ?? ($user ?? null);
    $mailBrandUser = $mailBrandUser instanceof \App\Models\User ? $mailBrandUser : null;
    $brandName = $mailBrandUser?->effectiveMailBrandName() ?? \App\Models\User::defaultMailBrandName();
    $brandTagline = $mailBrandUser?->effectiveMailBrandTagline() ?? \App\Models\User::defaultMailBrandTagline();
    $footerBlurb = $mailBrandUser?->effectiveMailFooterBlurb() ?? \App\Models\User::defaultMailFooterBlurb();
@endphp

<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $brandName }}
        </x-mail::header>
    </x-slot:header>

    {{ $slot }}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer :brand="$brandName" :tagline="$brandTagline" :footer-blurb="$footerBlurb">
            {{ $footerBlurb }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
