<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

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

        <div class="flex items-start gap-3">
            <div>
                <input id="show_invoice_ids" type="checkbox" name="show_invoice_ids" value="1"
                       @checked(old('show_invoice_ids', $user->show_invoice_ids))
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
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
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
            </div>
            <div>
                <x-input-label for="auto_receipt_emails" :value="__('Auto email paid receipts')" />
                <p class="text-sm text-gray-500">
                    {{ __('When invoices are marked paid, automatically send a receipt to the client email.') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('auto_receipt_emails')" />
            </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Invoice branding defaults</h3>
            <p class="text-xs text-gray-600">
                These values populate new invoices automatically. You can override them per invoice when needed.
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="branding_heading" :value="__('Invoice heading')" />
                    <x-text-input id="branding_heading" name="branding_heading" type="text" class="mt-1 block w-full"
                                  :value="old('branding_heading', $user->branding_heading)" placeholder="CryptoZing Invoice" />
                    <x-input-error class="mt-2" :messages="$errors->get('branding_heading')" />
                </div>
                <div>
                    <x-input-label for="billing_name" :value="__('Biller name')" />
                    <x-text-input id="billing_name" name="billing_name" type="text" class="mt-1 block w-full"
                                  :value="old('billing_name', $user->billing_name)" autocomplete="organization" />
                    <x-input-error class="mt-2" :messages="$errors->get('billing_name')" />
                </div>
                <div>
                    <x-input-label for="billing_email" :value="__('Biller email')" />
                    <x-text-input id="billing_email" name="billing_email" type="email" class="mt-1 block w-full"
                                  :value="old('billing_email', $user->billing_email)" autocomplete="email" />
                    <x-input-error class="mt-2" :messages="$errors->get('billing_email')" />
                </div>
                <div>
                    <x-input-label for="billing_phone" :value="__('Biller phone')" />
                    <x-text-input id="billing_phone" name="billing_phone" type="text" class="mt-1 block w-full"
                                  :value="old('billing_phone', $user->billing_phone)" autocomplete="tel" />
                    <x-input-error class="mt-2" :messages="$errors->get('billing_phone')" />
                </div>
            </div>
            <div>
                <x-input-label for="billing_address" :value="__('Biller address')" />
                <textarea id="billing_address" name="billing_address" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="123 Main Street&#10;Suite 100&#10;Denver, CO 80202">{{ old('billing_address', $user->billing_address) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('billing_address')" />
            </div>
            <div>
                <x-input-label for="invoice_footer_note" :value="__('Invoice footer note')" />
                <textarea id="invoice_footer_note" name="invoice_footer_note" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Net 7 · Send BTC only to the address above">{{ old('invoice_footer_note', $user->invoice_footer_note) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('invoice_footer_note')" />
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
            <h3 class="text-sm font-semibold text-gray-700">Invoice defaults</h3>
            <p class="text-xs text-gray-600">
                Memo text and payment terms will auto-fill new invoices unless you override them per invoice.
            </p>
            <div>
                <x-input-label for="invoice_default_description" :value="__('Default memo / description')" />
                <textarea id="invoice_default_description" name="invoice_default_description" rows="3"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                          placeholder="Weekly retainer for CryptoZing">{{ old('invoice_default_description', $user->invoice_default_description) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('invoice_default_description')" />
            </div>
            <div>
                <x-input-label for="invoice_default_terms_days" :value="__('Payment terms (days)')" />
                <x-text-input id="invoice_default_terms_days" name="invoice_default_terms_days" type="number" min="0" max="365"
                              class="mt-1 block w-full"
                              :value="old('invoice_default_terms_days', $user->invoice_default_terms_days)" />
                <p class="mt-1 text-xs text-gray-500">Set how many days after the invoice date the due date should default to. Leave blank to pick dates manually.</p>
                <x-input-error class="mt-2" :messages="$errors->get('invoice_default_terms_days')" />
            </div>
        </div>
    </form>
</section>
