<!DOCTYPE html>
@php
    $themePreference = auth()->user()?->theme ?? 'system';
    $isDark = $themePreference === 'dark';
    $providedTitle = trim((string) $attributes->get('title'));
    $pageTitle = $providedTitle !== '' ? $providedTitle . ' · ' . config('app.name', 'CryptoZing') : config('app.name', 'CryptoZing');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themePreference }}" class="{{ $isDark ? 'dark' : '' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $pageTitle }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
        @stack('page-favicon')
        @stack('head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
	    <body class="font-sans antialiased bg-gray-100 dark:bg-slate-900 text-gray-900 dark:text-slate-100">
	        <div class="min-h-screen">
	            @include('layouts.navigation')

	            @isset($header)
	                <header class="bg-white shadow dark:bg-slate-900/60 dark:ring-1 dark:ring-white/10">
	                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
        <script>
            (() => {
                const root = document.documentElement;
                const saved = localStorage.getItem('theme');
                const pref = saved || root.dataset.theme || 'system';
                const prefersDark = () => window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                const apply = (mode) => {
                    const useDark = mode === 'dark' || (mode === 'system' && prefersDark());
                    root.classList.toggle('dark', useDark);
                    root.dataset.themeApplied = useDark ? 'dark' : 'light';
                    root.dataset.theme = mode;
                };

                apply(pref);

                if (pref === 'system' && window.matchMedia) {
                    const mql = window.matchMedia('(prefers-color-scheme: dark)');
                    mql.addEventListener('change', () => apply(pref));
                }
            })();
        </script>
    </body>
</html>
