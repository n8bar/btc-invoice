@php
    $onboarding = $onboarding ?? false;
    $detailsClasses = $onboarding
        ? 'rounded border border-indigo-200 bg-indigo-50/70 p-3 text-xs text-slate-700 shadow-sm ring-1 ring-indigo-200/80 dark:border-indigo-400/35 dark:bg-indigo-950/30 dark:text-slate-200 dark:ring-indigo-400/25'
        : 'rounded border border-gray-200 bg-gray-50/60 p-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-300';
@endphp

<details class="{{ $detailsClasses }}"
         data-wallet-key-helper="{{ $onboarding ? 'onboarding' : 'default' }}"
         @if ($onboarding) data-getting-started-highlight="wallet-key-helper" @endif>
    <summary class="flex cursor-pointer items-center justify-between gap-3 text-sm font-medium text-slate-700 dark:text-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
        <span>Where do I find this?</span>
        @if ($onboarding)
            <span class="inline-flex items-center rounded-full border border-indigo-300 bg-white px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-indigo-700 dark:border-indigo-400/40 dark:bg-indigo-900/40 dark:text-indigo-200">
                Recommended for setup
            </span>
        @endif
    </summary>
    <div class="mt-3 space-y-3">
        <ol class="list-decimal space-y-1 pl-4">
            <li>Open your wallet and choose the account you want payments to land in.</li>
            <li>Go to Receive (or Account details) -> Advanced/export.</li>
            <li>Copy the account public key (often labeled xpub/zpub/vpub/tpub). Do not copy a single address.</li>
            <li>Paste here. You can verify below before saving.</li>
        </ol>
        <div class="grid gap-2 sm:grid-cols-2">
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Ledger Live</div>
                <div class="text-slate-600 dark:text-slate-300">Accounts -> Receive -> Account settings -> Advanced -> Extended public key.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Trezor Suite</div>
                <div class="text-slate-600 dark:text-slate-300">Accounts -> Receive -> Show public key.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Sparrow</div>
                <div class="text-slate-600 dark:text-slate-300">Wallet settings -> Keystore -> Export xpub.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Blockstream Green (iOS/Android)</div>
                <div class="text-slate-600 dark:text-slate-300">Account -> menu -> Export xpub.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">BlueWallet (iOS/Android)</div>
                <div class="text-slate-600 dark:text-slate-300">Wallet -> More -> Show XPUB.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                <div class="font-semibold text-slate-900 dark:text-slate-100">Nunchuk (iOS/Android)</div>
                <div class="text-slate-600 dark:text-slate-300">Wallet -> Manage -> Export xpub.</div>
            </div>
        </div>
        <p class="text-slate-500 dark:text-slate-300">This is a receive-only key. Never share or paste your seed phrase.</p>
    </div>
</details>
