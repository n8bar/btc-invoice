<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="space-y-2">
            <label for="email" class="block text-sm font-semibold auth-heading">{{ __('Email') }}</label>
            <x-text-input id="email" class="block w-full auth-input" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4 space-y-2">
            <label for="password" class="block text-sm font-semibold auth-heading">{{ __('Password') }}</label>
            <x-text-input id="password" class="block w-full auth-input" type="password" name="password" required autocomplete="new-password" />
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
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
</x-guest-layout>
