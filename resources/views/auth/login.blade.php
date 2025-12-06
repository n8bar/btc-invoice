<x-guest-layout>
    <div class="grid gap-10 lg:grid-cols-2 items-center">
        <div class="space-y-4 max-w-xl">
            <p class="text-sm font-semibold uppercase tracking-[0.2em] auth-muted">Welcome back</p>
            <h1 class="text-4xl font-semibold auth-heading leading-tight">
                Sign in to CryptoZing
            </h1>
        </div>

        <div class="auth-card shadow-indigo-900/30 backdrop-blur">

            @if (session('status'))
                <div class="mb-4 rounded-lg alert-success px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg alert-error px-4 py-3 text-sm">
                    <div class="font-semibold">We couldn’t sign you in.</div>
                    <ul class="mt-2 list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold auth-heading">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="auth-input w-full placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400 focus:ring-offset-0">
                </div>

                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold auth-heading">Password</label>
                    <input id="password" type="password" name="password" required autocomplete="current-password" class="auth-input w-full placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400 focus:ring-offset-0">
                </div>

                <div class="flex items-center justify-between text-sm auth-muted">
                    <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                        <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-400 bg-white text-indigo-600 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-white dark:border-white/40 dark:bg-slate-900/60 dark:text-indigo-200 dark:focus:ring-offset-transparent">
                        <span>Remember me</span>
                    </label>
                    @if (Route::has('password.request'))
                        <a class="font-semibold auth-link" href="{{ route('password.request') }}">
                            Forgot password?
                        </a>
                    @endif
                </div>

                <div class="pt-2 space-y-3">
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg px-4 py-3 text-sm font-semibold shadow-lg shadow-indigo-900/40 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0 btn-primary bg-indigo-600 text-white dark:bg-indigo-500">
                        Log in
                    </button>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-center rounded-lg px-4 py-3 text-sm font-semibold border border-gray-200 bg-white text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0 dark:border-white/25 dark:bg-white/10 dark:text-white dark:hover:bg-white/15">
                            Create a CryptoZing account
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</x-guest-layout>
