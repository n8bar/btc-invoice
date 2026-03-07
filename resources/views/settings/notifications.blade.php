<x-emoji-favicon symbol="🔔" bg="#FEF3C7" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Settings
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('settings.partials.tabs')

            <div class="overflow-hidden bg-white shadow sm:rounded-lg">
                <div class="p-6">
                    @if (session('status') === 'notification-settings-updated')
                        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800" style="border-color: currentColor;">
                            Saved notification settings.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-700">Receipts</h3>
                            <p class="text-xs text-gray-600">
                                Control automatic receipt delivery to clients after invoices are paid.
                            </p>
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
