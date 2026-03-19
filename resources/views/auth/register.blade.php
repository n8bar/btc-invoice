<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" x-data="{ showPassword: false, showPasswordConfirmation: false }">
        @csrf

        <!-- Name -->
        <div class="space-y-2">
            <label for="name" class="block text-sm font-semibold auth-heading">{{ __('Name') }}</label>
            <x-text-input id="name" class="block w-full auth-input" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4 space-y-2">
            <label for="email" class="block text-sm font-semibold auth-heading">{{ __('Email') }}</label>
            <x-text-input id="email" class="block w-full auth-input" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4 space-y-2">
            <label for="password" class="block text-sm font-semibold auth-heading">{{ __('Password') }}</label>
            <div class="relative">
                <x-text-input id="password" class="block w-full auth-input pr-12"
                                x-bind:type="showPassword ? 'text' : 'password'"
                                name="password"
                                required autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-lg px-3 auth-muted transition hover:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0 dark:hover:text-white"
                    x-on:click="showPassword = !showPassword"
                    x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'"
                    x-bind:title="showPassword ? 'Hide password' : 'Show password'"
                    x-bind:aria-pressed="showPassword.toString()"
                    aria-controls="password"
                >
                    <span class="sr-only" x-text="showPassword ? 'Hide password' : 'Show password'"></span>
                    <svg x-show="!showPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <circle cx="12" cy="12" r="3.25" />
                    </svg>
                    <svg x-show="showPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.73 5.2A9.77 9.77 0 0 1 12 5.12c6 0 9.75 6.88 9.75 6.88a18.95 18.95 0 0 1-4.03 4.72" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.62A18.7 18.7 0 0 0 2.25 12s3.75 6.88 9.75 6.88a9.9 9.9 0 0 0 3.03-.47" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88A3 3 0 0 0 12 15a2.96 2.96 0 0 0 2.12-.88" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 space-y-2">
            <label for="password_confirmation" class="block text-sm font-semibold auth-heading">{{ __('Confirm Password') }}</label>
            <div class="relative">
                <x-text-input id="password_confirmation" class="block w-full auth-input pr-12"
                                x-bind:type="showPasswordConfirmation ? 'text' : 'password'"
                                name="password_confirmation" required autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-lg px-3 auth-muted transition hover:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0 dark:hover:text-white"
                    x-on:click="showPasswordConfirmation = !showPasswordConfirmation"
                    x-bind:aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                    x-bind:title="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                    x-bind:aria-pressed="showPasswordConfirmation.toString()"
                    aria-controls="password_confirmation"
                >
                    <span class="sr-only" x-text="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"></span>
                    <svg x-show="!showPasswordConfirmation" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <circle cx="12" cy="12" r="3.25" />
                    </svg>
                    <svg x-show="showPasswordConfirmation" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.73 5.2A9.77 9.77 0 0 1 12 5.12c6 0 9.75 6.88 9.75 6.88a18.95 18.95 0 0 1-4.03 4.72" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.62A18.7 18.7 0 0 0 2.25 12s3.75 6.88 9.75 6.88a9.9 9.9 0 0 0 3.03-.47" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88A3 3 0 0 0 12 15a2.96 2.96 0 0 0 2.12-.88" />
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm auth-link font-semibold" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <button type="submit" class="ms-4 inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0">
                {{ __('Register') }}
            </button>
        </div>
    </form>
</x-guest-layout>
