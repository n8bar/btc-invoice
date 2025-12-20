<details class="rounded border border-gray-200 bg-gray-50/60 p-3 text-xs text-gray-600">
    <summary class="cursor-pointer text-sm font-medium text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">
        Where do I find this?
    </summary>
    <div class="mt-3 space-y-3">
        <ol class="list-decimal space-y-1 pl-4">
            <li>Open your wallet and choose the account you want payments to land in.</li>
            <li>Go to Receive (or Account details) -> Advanced/export.</li>
            <li>Copy the account public key (often labeled xpub/zpub/vpub/tpub). Do not copy a single address.</li>
            <li>Paste here. You can verify below before saving.</li>
        </ol>
        <div class="grid gap-2 sm:grid-cols-2">
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">Ledger Live</div>
                <div class="text-gray-600">Accounts -> Receive -> Account settings -> Advanced -> Extended public key.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">Trezor Suite</div>
                <div class="text-gray-600">Accounts -> Receive -> Show public key.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">Sparrow</div>
                <div class="text-gray-600">Wallet settings -> Keystore -> Export xpub.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">Blockstream Green (iOS/Android)</div>
                <div class="text-gray-600">Account -> menu -> Export xpub.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">BlueWallet (iOS/Android)</div>
                <div class="text-gray-600">Wallet -> More -> Show XPUB.</div>
            </div>
            <div class="rounded border border-gray-200 bg-white px-3 py-2">
                <div class="font-semibold text-gray-900">Nunchuk (iOS/Android)</div>
                <div class="text-gray-600">Wallet -> Manage -> Export xpub.</div>
            </div>
        </div>
        <p class="text-gray-500">This is a receive-only key. Never share or paste your seed phrase.</p>
    </div>
</details>
