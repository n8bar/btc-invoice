<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'CryptoZing') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased min-h-screen bg-gray-900 text-slate-100">
    <div class="relative overflow-hidden min-h-screen">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/60 via-gray-900 to-black pointer-events-none"></div>

        <div class="relative max-w-6xl mx-auto px-6 py-12 lg:py-16">
            @if (Route::has('login'))
                <div class="flex justify-end mb-10">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center rounded-md bg-white/10 px-4 py-2 text-sm font-semibold text-slate-100 shadow-sm ring-1 ring-white/20 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            Dashboard
                        </a>
                    @else
                        <div class="flex items-center gap-3">
                            <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-white/10 px-4 py-2 text-sm font-semibold text-slate-100 shadow-sm ring-1 ring-white/20 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                Log in
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                    Register
                                </a>
                            @endif
                        </div>
                    @endauth
                </div>
            @endif

            <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-3 rounded-full bg-white/10 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-indigo-200 ring-1 ring-white/20">
                        CryptoZing.app
                    </div>
                    <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-white sm:text-5xl">
                        Bitcoin invoicing that stays accurate end-to-end.
                    </h1>
                    <p class="text-lg text-slate-200 max-w-2xl">
                        Generate invoices with USD-first amounts, live BTC quotes, BIP21 QR links, and automated payment detection. Send, track, and settle with confidence—your wallet xpub, our on-chain watcher.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                Go to dashboard
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                Create account
                            </a>
                            <a href="{{ route('login') }}" class="inline-flex items-center rounded-md bg-white/10 px-5 py-3 text-sm font-semibold text-slate-100 shadow-sm ring-1 ring-white/20 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                Log in
                            </a>
                        @endauth
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm text-slate-200">
                        <div class="rounded-lg bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-xs uppercase text-indigo-200 font-semibold">Wallet-ready</div>
                            <div class="mt-1 font-semibold">BIP84 xpub + mempool watcher</div>
                        </div>
                        <div class="rounded-lg bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-xs uppercase text-indigo-200 font-semibold">Client-friendly</div>
                            <div class="mt-1 font-semibold">Public share links + receipts</div>
                        </div>
                        <div class="rounded-lg bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-xs uppercase text-indigo-200 font-semibold">Accurate</div>
                            <div class="mt-1 font-semibold">USD-first quoting, partials tracked</div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center lg:justify-end">
                    <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10 shadow-xl">
                        <img src="{{ asset('images/CZ.png') }}" alt="CryptoZing" class="mx-auto h-48 w-auto">
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
