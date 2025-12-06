<x-guest-layout>
    <div class="mb-4 text-sm auth-subtext">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div class="space-y-2">
            <label for="password" class="block text-sm font-semibold auth-heading">{{ __('Password') }}</label>
            <x-text-input id="password" class="block w-full auth-input"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <button type="submit" class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:ring-offset-0">
                {{ __('Confirm') }}
            </button>
        </div>
    </form>
</x-guest-layout>
