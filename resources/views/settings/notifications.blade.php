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
                    <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
                        <h3 class="text-sm font-semibold text-gray-700">Payment emails</h3>
                        <p class="text-sm text-gray-600">
                            Detected payments can send a narrow acknowledgment right away when the app can safely say only that a payment was detected.
                        </p>
                        <p class="text-sm text-gray-600">
                            Client receipts are sent manually after owner review from the paid invoice page. When a paid invoice still needs a receipt, the dashboard and invoice payment history will point you to the review/send action.
                        </p>
                        <p class="text-xs text-gray-500">
                            Extra review context may appear when multiple active on-chain payments or payment-correction rows are present in the invoice history.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
