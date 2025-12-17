<x-emoji-favicon symbol="🔐" bg="#DCFCE7" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Wallet Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4 space-y-2 text-sm text-gray-600">
                        <p>{{ __('Provide a BIP84 xpub so a unique Bitcoin address is generated per invoice.') }}</p>
                        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                            {{ __('Privacy note: an xpub lets anyone derive and monitor all addresses for this account. Keep it private and avoid sharing screenshots/logs.') }}
                        </div>
                        <div>
                            <a href="{{ route('help', ['from' => 'wallet-settings']) }}#xpub-safety"
                               class="inline-flex items-center text-sm font-medium text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                Helpful notes: xpub safety and why we ask
                            </a>
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @php
                        $xpubValue = old('bip84_xpub', optional($wallet)->bip84_xpub ?? '');
                    @endphp
                    <form method="POST" action="{{ route('wallet.settings.update') }}" class="space-y-6">
                        @csrf
                        @if ($defaultNetwork !== 'mainnet')
                            <div class="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                {{ __('Testnet (for testing only). Real payments require mainnet.') }}
                            </div>
                        @endif

                        <div>
                            <x-input-label for="bip84_xpub" :value="__('BIP84 xpub')" />
                            <x-text-input id="bip84_xpub" name="bip84_xpub" type="text" class="mt-1 block w-full"
                                          :value="$xpubValue" autocomplete="off" required />
                            <x-input-error class="mt-2" :messages="$errors->get('bip84_xpub')" />
                        </div>

                        <div>
                            <x-primary-button>
                                {{ __('Save Wallet Settings') }}
                            </x-primary-button>
                        </div>
                    </form>

                    {{-- Additional wallets UI hidden until feature is ready --}}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
