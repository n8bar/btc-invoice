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
                    <p class="mb-4 text-sm text-gray-600">
                        {{ __('Provide a BIP84 xpub so a unique Bitcoin address is generated per invoice. For testnet, paste the vpub for your external chain (m/84\'/1\'/0\'/0).') }}
                    </p>

                    @if (session('status'))
                        <div class="mb-4 rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    @php
                        $selectedNetwork = old('network', optional($wallet)->network ?? 'testnet');
                        $xpubValue = old('bip84_xpub', optional($wallet)->bip84_xpub ?? '');
                    @endphp
                    <form method="POST" action="{{ route('wallet.settings.update') }}" class="space-y-6">
                        @csrf
                        <div>
                            <x-input-label for="network" :value="__('Network')" />
                            <select id="network" name="network" class="mt-1 block w-full rounded border-gray-300" required>
                                @foreach (['testnet' => 'Bitcoin Testnet', 'mainnet' => 'Bitcoin Mainnet'] as $value => $label)
                                    <option value="{{ $value }}" @selected($selectedNetwork === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('network')" />
                        </div>

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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
