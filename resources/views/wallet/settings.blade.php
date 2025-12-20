<x-emoji-favicon symbol="🔐" bg="#DCFCE7" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Wallet Settings') }}
        </h2>
    </x-slot>

    @php
        $oldContext = old('form_context');
        $xpubValue = $oldContext === 'primary' ? old('bip84_xpub') : optional($wallet)->bip84_xpub ?? '';
        $additionalXpubValue = $oldContext === 'additional' ? old('bip84_xpub') : '';
        $additionalLabelValue = $oldContext === 'additional' ? old('label') : '';
        $expectedPrefix = $defaultNetwork === 'mainnet' ? 'xpub or zpub' : 'vpub or tpub';
        $isTestnet = $defaultNetwork !== 'mainnet';
        $accountErrors = $errors->getBag('walletAccount');
        $canAddAccount = $walletAccounts->count() < $maxAdditionalWallets;
    @endphp

    <div class="py-10">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8 space-y-6">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6 text-gray-900">
                    <div class="space-y-2 text-sm text-gray-600">
                        <p>{{ __('Connect a wallet account key so CryptoZing can generate a unique Bitcoin address for every invoice.') }}</p>
                        <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">
                            {{ __('Privacy note: this key lets anyone derive and monitor addresses for that account. Keep it private and avoid sharing screenshots or logs.') }}
                        </div>
                    </div>

                    @if (session('status'))
                        <div class="rounded border border-green-300 bg-green-50 p-3 text-sm text-green-800">
                            {{ session('status') }}
                        </div>
                    @endif

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-700">Primary wallet</h3>
                        <p class="mt-1 text-xs text-gray-500">This wallet receives all invoice payments for now.</p>

                        <form method="POST" action="{{ route('wallet.settings.update') }}"
                              class="mt-4 space-y-6"
                              x-data="walletValidation({
                                  validationUrl: '{{ route('wallet.settings.validate') }}',
                                  initialValue: @js($xpubValue),
                                  expectedPrefix: @js($expectedPrefix),
                                  hasServerError: @js($errors->has('bip84_xpub')),
                              })"
                              x-init="init()"
                              @submit.prevent="handleSubmit($event)">
                            @csrf
                            <input type="hidden" name="form_context" value="primary">

                            @if ($isTestnet)
                                <div class="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                    {{ __('Testnet (for testing only). Real payments require mainnet.') }}
                                </div>
                            @endif

                            <div>
                                <x-input-label for="bip84_xpub" :value="__('Wallet account key (xpub/zpub/vpub/tpub)')" />
                                <p class="mt-1 text-xs text-gray-500">
                                    Paste the account-level public key from your wallet. Never paste a seed phrase.
                                </p>
                                <x-text-input id="bip84_xpub" name="bip84_xpub" type="text"
                                              class="mt-2 block w-full"
                                              :value="$xpubValue"
                                              autocomplete="off"
                                              autocapitalize="none"
                                              autocorrect="off"
                                              spellcheck="false"
                                              @if ($errors->has('bip84_xpub')) autofocus @endif
                                              x-ref="input"
                                              x-model="value"
                                              @input="handleInput"
                                              @blur="handleBlur" />
                                <div class="mt-2 min-h-[4.5rem] space-y-2 text-xs" aria-live="polite">
                                    <div class="flex flex-wrap items-center gap-2 text-gray-500">
                                        <span>Expected format: <span class="font-medium text-gray-700">{{ $expectedPrefix }}</span></span>
                                        <button type="button"
                                                class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                                @click="validate({ force: true })">
                                            Re-run validation
                                        </button>
                                        <a href="{{ route('help', ['from' => 'wallet-settings']) }}#import-wallet-key"
                                           class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                            Need help?
                                        </a>
                                    </div>
                                    <div x-show="status === 'validating'" class="flex items-center gap-2 text-gray-500" role="status">
                                        <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-70" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                        </svg>
                                        <span>Validating address...</span>
                                    </div>
                                    <div x-show="status === 'success'" class="space-y-1 text-green-700">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.707a1 1 0 0 0-1.414-1.414L9 10.172 7.707 8.879a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4Z" clip-rule="evenodd" />
                                            </svg>
                                            <span x-text="message"></span>
                                        </div>
                                        <div class="rounded bg-green-50 px-2 py-1 font-mono text-[11px] text-green-900 break-all" x-text="address"></div>
                                    </div>
                                    <div x-show="status === 'error'" class="text-red-600" role="alert" x-text="message"></div>
                                    <x-input-error class="text-xs text-red-600" :messages="$errors->get('bip84_xpub')" />
                                </div>
                                <div class="mt-3">
                                    @include('wallet.partials.key-helper')
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <x-primary-button>Save wallet</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Additional wallets</h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Save extra wallet accounts for future multi-wallet selection. These are not active yet.
                            </p>
                        </div>

                        @if ($walletAccounts->isEmpty())
                            <p class="text-sm text-gray-600">No additional wallets saved yet.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($walletAccounts as $account)
                                    <div class="flex flex-col gap-2 rounded border border-gray-200 bg-gray-50/70 px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $account->label }}</div>
                                            <div class="text-xs text-gray-500">
                                                Network: {{ $account->network === 'mainnet' ? 'Mainnet' : 'Testnet' }}
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('wallet.settings.accounts.destroy', $account) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-danger-button type="submit">Remove</x-danger-button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <form method="POST" action="{{ route('wallet.settings.accounts.store') }}"
                              class="space-y-6"
                              x-data="walletValidation({
                                  validationUrl: '{{ route('wallet.settings.validate') }}',
                                  initialValue: @js($additionalXpubValue),
                                  expectedPrefix: @js($expectedPrefix),
                                  hasServerError: @js($accountErrors->has('bip84_xpub')),
                              })"
                              x-init="init()"
                              @submit.prevent="handleSubmit($event)">
                            @csrf
                            <input type="hidden" name="form_context" value="additional">

                            @if (! $canAddAccount)
                                <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                    {{ __('You have reached the additional wallet limit.') }}
                                </div>
                            @endif

                            <div class="{{ $canAddAccount ? '' : 'opacity-60' }}">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="additional_label" :value="__('Label')" />
                                        <x-text-input id="additional_label" name="label" type="text"
                                                      class="mt-1 block w-full"
                                                      :value="$additionalLabelValue"
                                                      autocomplete="off"
                                                      :disabled="! $canAddAccount" />
                                        <x-input-error class="mt-2 text-xs text-red-600" :messages="$accountErrors->get('label')" />
                                    </div>
                                    <div>
                                        <x-input-label for="additional_bip84_xpub" :value="__('Wallet account key (xpub/zpub/vpub/tpub)')" />
                                        <x-text-input id="additional_bip84_xpub" name="bip84_xpub" type="text"
                                                      class="mt-1 block w-full"
                                                      :value="$additionalXpubValue"
                                                      autocomplete="off"
                                                      autocapitalize="none"
                                                      autocorrect="off"
                                                      spellcheck="false"
                                                      @if ($accountErrors->has('bip84_xpub')) autofocus @endif
                                                      x-ref="input"
                                                      x-model="value"
                                                      @input="handleInput"
                                                      @blur="handleBlur"
                                                      :disabled="! $canAddAccount" />
                                        <div class="mt-2 min-h-[4.5rem] space-y-2 text-xs" aria-live="polite">
                                            <div class="flex flex-wrap items-center gap-2 text-gray-500">
                                                <span>Expected format: <span class="font-medium text-gray-700">{{ $expectedPrefix }}</span></span>
                                                <button type="button"
                                                        class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                                                        @click="validate({ force: true })"
                                                        @disabled(! $canAddAccount)>
                                                    Re-run validation
                                                </button>
                                                <a href="{{ route('help', ['from' => 'wallet-settings']) }}#import-wallet-key"
                                                   class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
                                                    Need help?
                                                </a>
                                            </div>
                                            <div x-show="status === 'validating'" class="flex items-center gap-2 text-gray-500" role="status">
                                                <svg class="h-4 w-4 animate-spin text-gray-400" viewBox="0 0 24 24" fill="none">
                                                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-70" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z"></path>
                                                </svg>
                                                <span>Validating address...</span>
                                            </div>
                                            <div x-show="status === 'success'" class="space-y-1 text-green-700">
                                                <div class="flex items-center gap-2">
                                                    <svg class="h-4 w-4 text-green-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.707a1 1 0 0 0-1.414-1.414L9 10.172 7.707 8.879a1 1 0 0 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4Z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span x-text="message"></span>
                                                </div>
                                                <div class="rounded bg-green-50 px-2 py-1 font-mono text-[11px] text-green-900 break-all" x-text="address"></div>
                                            </div>
                                            <div x-show="status === 'error'" class="text-red-600" role="alert" x-text="message"></div>
                                            <x-input-error class="text-xs text-red-600" :messages="$accountErrors->get('bip84_xpub')" />
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    @include('wallet.partials.key-helper')
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <x-primary-button :disabled="! $canAddAccount">Save additional wallet</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
