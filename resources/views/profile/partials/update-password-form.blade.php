<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-6 space-y-6" x-data="{ showCurrentPassword: false, showNewPassword: false, showPasswordConfirmation: false }">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_current_password" name="current_password" x-bind:type="showCurrentPassword ? 'text' : 'password'" class="block w-full pr-12" autocomplete="current-password" />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-md px-3 text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:hover:text-white dark:focus:ring-offset-slate-900"
                    x-on:click="showCurrentPassword = !showCurrentPassword"
                    x-bind:aria-label="showCurrentPassword ? 'Hide current password' : 'Show current password'"
                    x-bind:title="showCurrentPassword ? 'Hide current password' : 'Show current password'"
                    x-bind:aria-pressed="showCurrentPassword.toString()"
                    aria-controls="update_password_current_password"
                >
                    <span class="sr-only" x-text="showCurrentPassword ? 'Hide current password' : 'Show current password'"></span>
                    <svg x-show="!showCurrentPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <circle cx="12" cy="12" r="3.25" />
                    </svg>
                    <svg x-show="showCurrentPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.73 5.2A9.77 9.77 0 0 1 12 5.12c6 0 9.75 6.88 9.75 6.88a18.95 18.95 0 0 1-4.03 4.72" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.62A18.7 18.7 0 0 0 2.25 12s3.75 6.88 9.75 6.88a9.9 9.9 0 0 0 3.03-.47" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88A3 3 0 0 0 12 15a2.96 2.96 0 0 0 2.12-.88" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_password" name="password" x-bind:type="showNewPassword ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-md px-3 text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:hover:text-white dark:focus:ring-offset-slate-900"
                    x-on:click="showNewPassword = !showNewPassword"
                    x-bind:aria-label="showNewPassword ? 'Hide new password' : 'Show new password'"
                    x-bind:title="showNewPassword ? 'Hide new password' : 'Show new password'"
                    x-bind:aria-pressed="showNewPassword.toString()"
                    aria-controls="update_password_password"
                >
                    <span class="sr-only" x-text="showNewPassword ? 'Hide new password' : 'Show new password'"></span>
                    <svg x-show="!showNewPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <circle cx="12" cy="12" r="3.25" />
                    </svg>
                    <svg x-show="showNewPassword" style="display: none;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="h-5 w-5" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.73 5.2A9.77 9.77 0 0 1 12 5.12c6 0 9.75 6.88 9.75 6.88a18.95 18.95 0 0 1-4.03 4.72" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.62A18.7 18.7 0 0 0 2.25 12s3.75 6.88 9.75 6.88a9.9 9.9 0 0 0 3.03-.47" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.88 9.88A3 3 0 0 0 12 15a2.96 2.96 0 0 0 2.12-.88" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
            <div class="relative mt-1">
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" x-bind:type="showPasswordConfirmation ? 'text' : 'password'" class="block w-full pr-12" autocomplete="new-password" />
                <button
                    type="button"
                    class="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-md px-3 text-slate-500 transition hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-slate-300 dark:hover:text-white dark:focus:ring-offset-slate-900"
                    x-on:click="showPasswordConfirmation = !showPasswordConfirmation"
                    x-bind:aria-label="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                    x-bind:title="showPasswordConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                    x-bind:aria-pressed="showPasswordConfirmation.toString()"
                    aria-controls="update_password_password_confirmation"
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
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
