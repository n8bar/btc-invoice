<x-emoji-favicon symbol="💡" bg="#E0F2FE" />
<x-public-layout title="Helpful Notes">
    @push('head')
        <meta name="description" content="Helpful notes on wallet safety (xpub/zpub), invoices, payments, and privacy for CryptoZing Bitcoin invoicing.">
        <link rel="canonical" href="{{ route('help') }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="Helpful Notes · {{ config('app.name', 'CryptoZing') }}">
        <meta property="og:description" content="Helpful notes on wallet safety (xpub/zpub), invoices, payments, and privacy for CryptoZing Bitcoin invoicing.">
        <meta property="og:url" content="{{ route('help') }}">
    @endpush

    <x-slot name="header">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-slate-100">Helpful Notes</h1>
            <p class="text-sm text-gray-600 dark:text-slate-300">
                Plain-language explanations for common questions about wallets, invoices, payments, and privacy.
            </p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-8">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                <p class="text-sm text-gray-700 dark:text-slate-200">
                    These notes are meant to help you make safe choices and understand what CryptoZing can and can’t access.
                    If a page or email ever asks for a <b>seed phrase</b> or <b>private key</b>, stop — that’s never required.
                </p>
            </div>

            <nav aria-label="On this page" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-slate-100">On this page</h2>
                <ul class="mt-3 grid gap-2 text-sm text-gray-700 dark:text-slate-200 sm:grid-cols-2">
                    <li><a class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200" href="#wallet-security">Wallet &amp; Security</a></li>
                    <li><a class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200" href="#invoices">Invoices</a></li>
                    <li><a class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200" href="#payments">Payments</a></li>
                    <li><a class="text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200" href="#privacy">Privacy</a></li>
                </ul>
            </nav>

            <section id="wallet-security" class="scroll-mt-24 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Wallet &amp; Security</h2>

                <article id="xpub-safety" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
	                        <div>
	                            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Extended public keys (xpub / zpub): what they are and how we use them</h3>
	                            <p class="mt-1 text-sm text-gray-600 dark:text-slate-300">
	                                CryptoZing asks for a BIP84 account extended public key (usually <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-medium text-gray-900 dark:bg-white/10 dark:text-slate-100">zpub</code> or <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-medium text-gray-900 dark:bg-white/10 dark:text-slate-100">xpub</code>) so it can generate a unique receiving address per invoice and watch for on-chain payments.
	                            </p>
	                        </div>
                        @if (!empty($backLink))
                            <div class="shrink-0">
                                <a href="{{ $backLink['url'] }}" class="inline-flex items-center text-sm font-medium text-indigo-700 hover:text-indigo-900 underline-offset-2 hover:underline dark:text-indigo-300 dark:hover:text-indigo-200">
                                    ← {{ $backLink['label'] }}
                                </a>
                            </div>
                        @endif
                    </div>

                    <dl class="mt-4 space-y-4 text-sm text-gray-700 dark:text-slate-200">
                        <div>
                            <dt class="font-semibold text-gray-900 dark:text-slate-100">What is an xpub/zpub?</dt>
                            <dd class="mt-1">
                                It’s a <span class="font-medium">receive-only</span> key that lets software derive many Bitcoin addresses for a single wallet account. Different wallets label them as <span class="font-medium">xpub</span> or <span class="font-medium">zpub</span>, but they’re both extended public keys.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900 dark:text-slate-100">Why do we ask for it?</dt>
                            <dd class="mt-1">
                                To generate a fresh address per invoice (better bookkeeping and less address reuse) and to automatically detect payments sent to those addresses.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900 dark:text-slate-100">What can’t it do?</dt>
                            <dd class="mt-1">
                                An xpub/zpub <span class="font-medium">cannot spend</span> your bitcoin. It can’t sign transactions, move funds, or reveal your private keys.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-900 dark:text-slate-100">What are the privacy implications?</dt>
                            <dd class="mt-1">
                                Anyone who has this key can derive and monitor the addresses in that account. Treat it like sensitive data: don’t share it, and avoid posting screenshots or logs that include it.
                            </dd>
                        </div>
	                        <div>
	                            <dt class="font-semibold text-gray-900 dark:text-slate-100">Best practice (recommended)</dt>
	                            <dd class="mt-1">
	                                Use a dedicated “Invoices” wallet or account for CryptoZing and share only that account’s xpub/zpub. This keeps invoicing activity separate from personal holdings, reduces address-linking exposure, and makes it easy to sweep funds to an exchange/off-ramp or cold storage.
	                            </dd>
	                        </div>
	                        <div>
	                            <dt class="font-semibold text-gray-900 dark:text-slate-100">What we will never ask for</dt>
	                            <dd class="mt-1">
	                                CryptoZing will never ask for your seed phrase, private keys, wallet file, or for you to “send a test transaction” to unlock anything.
	                            </dd>
	                        </div>
	                    </dl>
	                </article>

	                <article id="import-wallet-key" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
	                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Importing your wallet account key (xpub / zpub)</h3>
	                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
	                        To connect a wallet to CryptoZing, you copy your wallet’s <span class="font-medium">account-level</span> extended public key and paste it into <span class="font-medium">Wallet Settings</span>.
	                        This lets CryptoZing generate a unique receiving address per invoice.
	                    </p>

	                    <div class="mt-4 space-y-5 text-sm text-gray-700 dark:text-slate-200">
	                        <div>
	                            <h4 class="font-semibold text-gray-900 dark:text-slate-100">Recommended: BlueWallet</h4>
	                            <ol class="mt-2 list-decimal space-y-2 pl-5">
	                                <li>Create a new <span class="font-medium">Bitcoin</span> wallet (recommended: name it “CryptoZing Invoices”).</li>
	                                <li>Open that wallet, tap the menu (<span class="font-medium">⋯</span>), then choose <span class="font-medium">Backup/Export</span>.</li>
	                                <li>Copy the extended public key (often starts with <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-medium text-gray-900 dark:bg-white/10 dark:text-slate-100">zpub</code> or <code class="rounded bg-gray-100 px-1 py-0.5 text-xs font-medium text-gray-900 dark:bg-white/10 dark:text-slate-100">xpub</code>).</li>
	                                <li>Paste it into CryptoZing’s <span class="font-medium">Wallet Settings</span>.</li>
	                            </ol>
	                        </div>

	                        <div>
	                            <h4 class="font-semibold text-gray-900 dark:text-slate-100">Other wallets</h4>
	                            <ul class="mt-2 list-disc space-y-2 pl-5">
	                                <li>Look for <span class="font-medium">Account details</span>, <span class="font-medium">Wallet details</span>, or <span class="font-medium">Advanced</span> → “<span class="font-medium">Extended public key</span>” / “<span class="font-medium">Master public key</span>”.</li>
	                                <li>Make sure you’re exporting an <span class="font-medium">account key</span>, not a single receive address.</li>
	                                <li>If your wallet supports multiple accounts, create a dedicated <span class="font-medium">Invoices</span> account and export the key for that account.</li>
	                            </ul>
	                        </div>

	                        <div>
	                            <h4 class="font-semibold text-gray-900 dark:text-slate-100">After you get paid</h4>
	                            <ul class="mt-2 list-disc space-y-2 pl-5">
	                                <li>It’s normal to sweep funds to an exchange/off-ramp or cold storage to reduce volatility exposure.</li>
	                                <li>CryptoZing never has custody and cannot send funds — any sweep/refund is a transaction you send from your wallet.</li>
	                            </ul>
	                        </div>

	                        <div>
	                            <h4 class="font-semibold text-gray-900 dark:text-slate-100">Advanced: importing a seed phrase (last resort)</h4>
	                            <p class="mt-2">
	                                If you’re determined to keep a wallet that won’t export an xpub/zpub, you <span class="font-medium">can</span> import that wallet’s seed phrase into a wallet that can export one — but this is risky.
	                                Importing a seed phrase gives that app full spending access. Only do this if you understand the security tradeoffs.
	                            </p>
	                        </div>
	                    </div>
	                </article>
	            </section>

            <section id="invoices" class="scroll-mt-24 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Invoices</h2>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Why invoices use unique addresses</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
                        Each invoice gets its own receiving address so payments are easier to match and you avoid reusing addresses.
                    </p>
                </div>

                <article id="rate-calculation" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">How the BTC rate is calculated</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
                        CryptoZing treats the invoice amount in USD as the source of truth and converts to BTC using a live BTC/USD spot rate.
                    </p>
	                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-700 dark:text-slate-200">
	                        <li><span class="font-medium">Data source:</span> we fetch a BTC/USD spot rate and may cache it briefly to avoid excessive rate lookups.</li>
	                        <li><span class="font-medium">Conversion:</span> BTC = USD ÷ (USD per BTC), rounded to 8 decimal places, (satoshi level accuracy).</li>
	                        <li><span class="font-medium">In the unlikely event rates are unavailable:</span> we fall back to the last known rate stored with the invoice.</li>
	                        <li><span class="font-medium">Outstanding balance:</span> Bitcoin payments can be split across multiple transactions. Until the confirmed total meets the invoice amount, we show the remaining amount due as the outstanding balance (full amount, remainder, or $0 when paid).</li>
	                    </ul>
	                    <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
	                        <span class="font-medium">Example:</span> Alice invoices Bob for <span class="font-medium">$1,000</span>. Bob sends <span class="font-medium">$600 worth of BTC</span> first. Once that payment confirms, the invoice shows <span class="font-medium">Partial</span> and an <span class="font-medium">Outstanding balance of $400</span>, and the QR/BIP21 updates to the remaining $400. When Bob sends the rest, the invoice becomes <span class="font-medium">Paid</span> and the outstanding balance becomes <span class="font-medium">$0</span>.
	                    </p>
	                </article>
	            </section>

            <section id="payments" class="scroll-mt-24 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Payments</h2>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Pending vs confirmed</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
                        Bitcoin payments may appear quickly but can take time to confirm. CryptoZing tracks what’s detected on-chain and updates invoice status once confirmations meet the configured threshold.
                    </p>
                </div>

	                <article id="partial-payments" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
	                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Partial payments</h3>
	                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
	                        If a client pays in multiple transactions, CryptoZing records each payment and shows what’s still outstanding.
                    </p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-gray-700 dark:text-slate-200">
                        <li>Invoices move from <span class="font-medium">Pending</span> (detected) to <span class="font-medium">Partial</span> (some confirmed) when more is still due, then to <span class="font-medium">Paid</span> when the confirmed total meets or exceeds the invoice amount.</li>
                        <li>Each payment’s USD value is credited using the BTC/USD rate at the time we detected it, so later volatility doesn’t change what was credited.</li>
	                        <li>The QR/BIP21 always targets the current outstanding balance so clients can finish payment without overpaying.</li>
	                    </ul>
	                </article>

	                <article id="overpayments" class="scroll-mt-24 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
	                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Overpayments</h3>
	                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
	                        If a client sends more than the invoice amount, CryptoZing records the full amount received. The invoice will still be marked paid once the confirmed total meets or exceeds the invoice amount.
	                    </p>
	                    <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
	                        Overpayments are treated as gratuities by default. If it was accidental, coordinate with your client to refund or apply the surplus as a credit.
	                    </p>
	                    <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
	                        <span class="font-medium">Example:</span> Alice invoices Bob for <span class="font-medium">$1,000</span>. Bob accidentally sends <span class="font-medium">$1,200</span> worth of BTC. CryptoZing records the full payment and marks the invoice paid. If Alice wants to refund the extra, she sends that transaction from her own wallet — CryptoZing cannot send funds on her behalf.
	                    </p>
	                    <p class="mt-3 text-sm text-gray-700 dark:text-slate-200">
	                        CryptoZing does not send funds and never has custody of your bitcoin. Any repayment/refund is your responsibility to send from your own wallet.
	                    </p>
	                </article>
	            </section>

            <section id="privacy" class="scroll-mt-24 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Privacy</h2>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/60">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">Public invoice links</h3>
                    <p class="mt-1 text-sm text-gray-700 dark:text-slate-200">
                        Public invoice links are designed for sharing with a specific client. However, anyone with the link can view it — share thoughtfully.
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-public-layout>
