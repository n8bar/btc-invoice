<x-emoji-favicon symbol="🔔" bg="#FEF3C7" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col">
            <h2 class="mb-4 text-xl font-semibold leading-tight text-gray-800">
                Settings
            </h2>
            <div class="mt-8">
                @include('settings.partials.tabs')
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="p-6 space-y-6">
                    @if (session('status') === 'notification-settings-updated')
                        <div class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800" style="border-color: currentColor;">
                            Saved notification settings.
                        </div>
                    @elseif (session('status') === 'notification-preview-sent')
                        <div class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800" style="border-color: currentColor;">
                            Sent a branded test email to {{ session('preview_email') }}.
                        </div>
                    @elseif (session('status') === 'notification-preview-throttled')
                        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800" style="border-color: currentColor;">
                            Test email already sent recently. Please wait a minute before sending another.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-4 space-y-4">
                            <div class="space-y-2">
                                <h3 class="text-sm font-semibold text-gray-700">Mail branding</h3>
                                <p class="text-xs text-gray-600">
                                    These fields only change the shared mail shell for active notification emails. Payment acknowledgments, receipts, paid notices, and alerts keep their own standard subject and body copy.
                                </p>
                                <p class="text-xs text-gray-500">
                                    Fields start with the current CryptoZing defaults. Clear any field to fall back to the shipped default again.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label for="mail_brand_name" :value="__('Brand name')" />
                                    <x-text-input id="mail_brand_name" name="mail_brand_name" type="text" class="mt-1 block w-full"
                                                  :value="old('mail_brand_name', $user->effectiveMailBrandName())"
                                                  :placeholder="\App\Models\User::defaultMailBrandName()" />
                                    <p class="mt-1 text-xs text-gray-500">Shown in the shared mail header.</p>
                                    <x-input-error class="mt-2" :messages="$errors->get('mail_brand_name')" />
                                </div>

                                <div>
                                    <x-input-label for="mail_brand_tagline" :value="__('Short tagline')" />
                                    <x-text-input id="mail_brand_tagline" name="mail_brand_tagline" type="text" class="mt-1 block w-full"
                                                  :value="old('mail_brand_tagline', $user->effectiveMailBrandTagline())"
                                                  :placeholder="\App\Models\User::defaultMailBrandTagline()" />
                                    <p class="mt-1 text-xs text-gray-500">Shown under the brand name in the shared mail header.</p>
                                    <x-input-error class="mt-2" :messages="$errors->get('mail_brand_tagline')" />
                                </div>
                            </div>

                            <div>
                                <x-input-label for="mail_footer_blurb" :value="__('Footer blurb')" />
                                <textarea id="mail_footer_blurb" name="mail_footer_blurb" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          placeholder="{{ \App\Models\User::defaultMailFooterBlurb() }}">{{ old('mail_footer_blurb', $user->effectiveMailFooterBlurb()) }}</textarea>
                                <p class="mt-1 text-xs text-gray-500">Shown in the shared footer under active notification emails.</p>
                                <x-input-error class="mt-2" :messages="$errors->get('mail_footer_blurb')" />
                            </div>

                            <div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white p-4">
                                <div>
                                    <input id="show_mail_logo" type="checkbox" name="show_mail_logo" value="1"
                                           @checked(old('show_mail_logo', $user->shouldShowMailLogo()))
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                </div>
                                <div>
                                    <x-input-label for="show_mail_logo" :value="__('Show the default CryptoZing logo in email headers')" />
                                    <p class="text-sm text-gray-500">
                                        Use the default CryptoZing logo for now, or turn it off. Custom logo uploads are not available yet.
                                    </p>
                                    <x-input-error class="mt-2" :messages="$errors->get('show_mail_logo')" />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>Save settings</x-primary-button>
                        </div>
                    </form>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-gray-700">Send yourself a test email</h3>
                            <p class="text-xs text-gray-600">
                                Save settings first, then send a branded test message to {{ $user->email }} using the current saved mail-branding settings.
                            </p>
                            <p class="text-xs text-gray-500">
                                This does not send to clients and does not create an invoice delivery-history row.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('settings.notifications.preview') }}">
                            @csrf
                            <x-secondary-button type="submit">Send me a test email</x-secondary-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
