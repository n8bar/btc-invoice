<x-emoji-favicon symbol="🧾" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Invoice Settings
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="p-6">
                    @if (session('status') === 'invoice-settings-updated')
                        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800" style="border-color: currentColor;">
                            Saved invoice settings.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.invoice.update') }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Branding &amp; footer</h3>
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

                        <div class="flex items-center gap-4">
                            <x-primary-button>Save settings</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
