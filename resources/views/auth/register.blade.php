<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
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

            <x-text-input id="password" class="block w-full auth-input"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4 space-y-2">
            <label for="password_confirmation" class="block text-sm font-semibold auth-heading">{{ __('Confirm Password') }}</label>

            <x-text-input id="password_confirmation" class="block w-full auth-input"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

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
