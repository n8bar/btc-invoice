<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    @php
        $profileFieldErrors = collect([
            'name',
            'email',
            'show_invoice_ids',
            'auto_receipt_emails',
            'show_overpayment_gratuity_note',
            'show_qr_refresh_reminder',
        ])->flatMap(fn ($field) => $errors->get($field))->filter()->values();
    @endphp

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        @if ($profileFieldErrors->isNotEmpty())
            <div id="profile-error-summary" class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800" role="alert" aria-live="assertive" tabindex="-1" style="border-color: currentColor;">
                <p class="font-semibold">Please review the highlighted fields.</p>
                <ul class="mt-2 list-disc list-inside space-y-1">
                    @foreach ($profileFieldErrors as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('status') === 'profile-updated')
            <div class="rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800" role="status" aria-live="polite" tabindex="-1" style="border-color: currentColor;">
                Saved profile changes.
            </div>
        @endif

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Workspace preferences</h3>
            <p class="text-xs text-gray-600">
                Manage owner-facing defaults that affect your day-to-day workflow.
            </p>

            <div class="flex items-start gap-3">
                <div>
                    <input id="show_invoice_ids" type="checkbox" name="show_invoice_ids" value="1"
                           @checked(old('show_invoice_ids', $user->show_invoice_ids))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
                </div>
                <div>
                    <x-input-label for="show_invoice_ids" :value="__('Show invoice IDs in list')" />
                    <p class="text-sm text-gray-500">
                        {{ __('Display the internal invoice ID as the first column on the invoice list.') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('show_invoice_ids')" />
                </div>
            </div>

            <div class="flex items-start gap-3">
                <div>
                    <input id="auto_receipt_emails" type="checkbox" name="auto_receipt_emails" value="1"
                           @checked(old('auto_receipt_emails', $user->auto_receipt_emails))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
                </div>
                <div>
                    <x-input-label for="auto_receipt_emails" :value="__('Auto email paid receipts')" />
                    <p class="text-sm text-gray-500">
                        {{ __('When invoices are marked paid, automatically send a receipt to the client email.') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('auto_receipt_emails')" />
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Client-facing payment notes</h3>
            <p class="text-xs text-gray-600">
                Choose which explanatory notes appear for clients in invoice show, public, and print views.
            </p>

            <div class="flex items-start gap-3">
                <div>
                    <input id="show_overpayment_gratuity_note" type="checkbox" name="show_overpayment_gratuity_note" value="1"
                           @checked(old('show_overpayment_gratuity_note', $user->show_overpayment_gratuity_note))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
                </div>
                <div>
                    <x-input-label for="show_overpayment_gratuity_note" :value="__('Show overpayment gratuity note to clients')" />
                    <p class="text-sm text-gray-500">
                        {{ __('Display the gratuity guidance note in invoice show/public/print views when overpayments are possible.') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('show_overpayment_gratuity_note')" />
                </div>
            </div>

            <div class="flex items-start gap-3">
                <div>
                    <input id="show_qr_refresh_reminder" type="checkbox" name="show_qr_refresh_reminder" value="1"
                           @checked(old('show_qr_refresh_reminder', $user->show_qr_refresh_reminder))
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1">
                </div>
                <div>
                    <x-input-label for="show_qr_refresh_reminder" :value="__('Show QR refresh reminder to clients')" />
                    <p class="text-sm text-gray-500">
                        {{ __('Display the QR staleness reminder near payment QR blocks in invoice show/public/print views.') }}
                    </p>
                    <x-input-error class="mt-2" :messages="$errors->get('show_qr_refresh_reminder')" />
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save profile') }}</x-primary-button>
        </div>

    </form>
</section>
