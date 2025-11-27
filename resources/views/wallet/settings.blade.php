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
                    <p class="mb-4 text-sm text-gray-600">
                        {{ __('Provide a BIP84 xpub so a unique Bitcoin address is generated per invoice. The network is set from WALLET_NETWORK (currently :network).', ['network' => strtoupper($defaultNetwork)]) }}
                    </p>

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
                        <div class="flex items-center justify-between rounded border border-gray-200 bg-gray-50 px-3 py-2">
                            <span class="text-sm font-semibold text-gray-800">Network</span>
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                {{ $defaultNetwork === 'mainnet' ? 'Bitcoin Mainnet' : 'Bitcoin Testnet' }}
                            </span>
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

                    <div class="mt-10 border-t border-gray-100 pt-6">
                        <h3 class="text-base font-semibold text-gray-800">Additional wallets</h3>
                        <p class="text-sm text-gray-600">
                            Store extra xpubs for upcoming multi-wallet selection. These accounts aren’t used yet, but saving them now makes future switching easier.
                        </p>

                        <div class="mt-4 space-y-3">
                            @forelse ($walletAccounts as $account)
                                <div class="rounded border border-gray-200 bg-gray-50 px-4 py-3 flex items-start justify-between gap-4">
                                    <div class="text-sm text-gray-700">
                                        <div class="font-semibold text-gray-900">{{ $account->label }}</div>
                                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ strtoupper($account->network) }}</div>
                                        <div class="mt-1 font-mono text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($account->bip84_xpub, 40) }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('wallet.settings.accounts.destroy', $account) }}"
                                          onsubmit="return confirm('Remove {{ $account->label }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-secondary-button type="submit">Remove</x-secondary-button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No additional wallets saved yet.</p>
                            @endforelse
                        </div>

                        @if ($walletAccounts->count() < $maxAdditionalWallets)
                            <form method="POST" action="{{ route('wallet.settings.accounts.store') }}" class="mt-6 space-y-4">
                                @csrf
                                <div>
                                    <x-input-label for="additional_label" :value="__('Label')" />
                                    <x-text-input id="additional_label" name="label" type="text"
                                                  class="mt-1 block w-full"
                                                  value="{{ old('label') }}" placeholder="Cold storage" />
                                    <x-input-error class="mt-2" :messages="$errors->walletAccount?->get('label')" />
                                </div>
                                <div class="flex items-center justify-between rounded border border-gray-200 bg-gray-50 px-3 py-2">
                                    <span class="text-sm font-semibold text-gray-800">Network</span>
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                                        {{ $defaultNetwork === 'mainnet' ? 'Bitcoin Mainnet' : 'Bitcoin Testnet' }}
                                    </span>
                                </div>
                                <div>
                                    <x-input-label for="additional_bip84_xpub" :value="__('BIP84 xpub')" />
                                    <x-text-input id="additional_bip84_xpub" name="bip84_xpub" type="text"
                                                  class="mt-1 block w-full"
                                                  value="{{ old('bip84_xpub') }}" autocomplete="off" />
                                    <x-input-error class="mt-2" :messages="$errors->walletAccount?->get('bip84_xpub')" />
                                </div>
                                <div class="flex items-center justify-between">
                                    <x-secondary-button type="submit">Save additional wallet</x-secondary-button>
                                    <span class="text-xs text-gray-500">You can save {{ $maxAdditionalWallets - $walletAccounts->count() }} more.</span>
                                </div>
                            </form>
                        @else
                            <p class="mt-6 text-sm text-gray-500">
                                You have saved the maximum of {{ $maxAdditionalWallets }} additional wallets. Remove one to add another.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
