<!DOCTYPE html>
@php
    $brand = 'CryptoZing';
    $providedTitle = trim((string) $attributes->get('title'));
    $pageTitle = $providedTitle !== '' ? $providedTitle : \App\Support\PageTitle::resolve(request());
    $documentTitle = $pageTitle !== '' ? $brand . ' - ' . $pageTitle : $brand;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $documentTitle }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet" />

    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @stack('page-favicon')

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --bg-base: #0b1220;
            --bg-overlay: radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.25), transparent 45%), radial-gradient(circle at 80% 0%, rgba(56, 189, 248, 0.2), transparent 40%), linear-gradient(180deg, #0b1220 0%, #060913 100%);
            --bg-card: rgba(255, 255, 255, 0.06);
            --border: rgba(255, 255, 255, 0.15);
            --text-primary: #e2e8f0;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --button-primary: #6366f1;
            --button-primary-text: #ffffff;
            --button-secondary-bg: rgba(255, 255, 255, 0.05);
            --button-secondary-border: rgba(255, 255, 255, 0.15);
            --input-bg: rgba(255, 255, 255, 0.06);
            --input-border: rgba(255, 255, 255, 0.2);
            --success-bg: rgba(34, 197, 94, 0.12);
            --success-border: rgba(74, 222, 128, 0.35);
            --success-text: #bbf7d0;
            --error-bg: rgba(248, 113, 113, 0.15);
            --error-border: rgba(248, 113, 113, 0.45);
            --error-text: #fecdd3;
            --link: #c7d2fe;
            --link-hover: #e0e7ff;
        }

        html[data-theme="light"] {
            --bg-base: #f8fafc;
            --bg-overlay: radial-gradient(circle at 15% 25%, #e0e7ff 0, transparent 45%), radial-gradient(circle at 85% 10%, #cffafe 0, transparent 40%), linear-gradient(180deg, #eef2ff 0%, #f8fafc 100%);
            --bg-card: #ffffff;
            --border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #1e293b;
            --text-muted: #475569;
            --button-primary: #4f46e5;
            --button-primary-text: #ffffff;
            --button-secondary-bg: #ffffff;
            --button-secondary-border: #e2e8f0;
            --input-bg: #f8fafc;
            --input-border: #e2e8f0;
            --success-bg: #ecfdf3;
            --success-border: #bbf7d0;
            --success-text: #166534;
            --error-bg: #fef2f2;
            --error-border: #fecdd3;
            --error-text: #991b1b;
            --link: #4338ca;
            --link-hover: #312e81;
        }

        body {
            background: var(--bg-base);
            color: var(--text-primary);
        }

        .auth-shell { color: var(--text-primary); }
        .auth-heading { color: var(--text-primary); }
        .auth-subtext { color: var(--text-secondary); }
        .auth-muted { color: var(--text-muted); }
        .auth-link { color: var(--link); }
        .auth-link:hover { color: var(--link-hover); }
        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: clamp(2rem, 3vw, 2.5rem);
            border-radius: 1.25rem;
        }
        .auth-backdrop { background: var(--bg-overlay); }
    </style>
</head>
<body class="min-h-screen font-['Instrument_Sans'] antialiased auth-shell">
    <div class="auth-backdrop absolute inset-0 pointer-events-none"></div>

    <div class="relative min-h-screen flex flex-col">
        @php
            $taglines = [
                'Trusted access to your invoicing hub',
                'Secure portal for billing and receivables',
                'Safe access to your on-chain invoicing',
                'Secure access to your on-chain receivables',
                'Secure access to your invoice manager',
                'Safe sign-in to your invoicing stack',
            ];
            $authTagline = session('auth_tagline');
            if (!$authTagline) {
                $sessionId = session()->getId() ?: (string) microtime(true);
                $index = abs(crc32($sessionId)) % count($taglines);
                $authTagline = $taglines[$index];
                session(['auth_tagline' => $authTagline]);
            }
        @endphp

        <header class="px-6 py-6 sm:px-10 flex items-center justify-between">
            <a href="/" class="inline-flex items-center gap-3 rounded-full bg-white/5 px-4 py-2 ring-1 ring-white/10 hover:bg-white/10 transition">
                <img src="{{ asset('images/CZ.png') }}" alt="CryptoZing" class="h-10 w-auto">
                <span class="text-lg font-semibold tracking-tight auth-heading">CryptoZing</span>
            </a>
            @if ($authTagline)
                <div class="hidden sm:block text-sm auth-muted">
                    {{ $authTagline }}
                </div>
            @endif
        </header>

        <main class="relative flex-1 px-1 sm:px-10 pb-12">
            <div class="max-w-5xl mx-auto">
                {{ $slot }}
            </div>
        </main>
    </div>
    <script>
        (() => {
            const root = document.documentElement;
            const applyTheme = (mode) => {
                root.dataset.theme = mode;
            };

            const prefers = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
            const initial = prefers ? (prefers.matches ? 'dark' : 'light') : 'dark';
            applyTheme(initial);

            if (prefers && typeof prefers.addEventListener === 'function') {
                prefers.addEventListener('change', (event) => {
                    applyTheme(event.matches ? 'dark' : 'light');
                });
            }
        })();
    </script>
</body>
</html>
