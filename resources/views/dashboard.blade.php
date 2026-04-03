<x-emoji-favicon symbol="📊" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500">At-a-glance invoice and payment health.</p>
            </div>
            <div class="w-full space-y-2 sm:w-auto sm:space-y-0 sm:flex sm:flex-wrap sm:items-center sm:justify-end sm:gap-2">
                @php
                    $newClientButtonClasses = ($hasClients ?? false)
                        ? 'inline-flex w-full items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap sm:w-auto'
                        : 'inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap sm:w-auto';
                @endphp
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-none sm:gap-2">
                    <a href="{{ route('clients.create') }}" class="{{ $newClientButtonClasses }}">
                        <span aria-hidden="true" class="mr-1">👤</span>
                        <span>New client</span>
                    </a>
                    <a href="{{ route('invoices.create') }}" class="inline-flex w-full items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap sm:w-auto">
                        <span aria-hidden="true" class="mr-1">🧾</span>
                        <span>New invoice</span>
                    </a>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-none sm:gap-2">
                    <a href="{{ route('clients.index') }}" class="inline-flex w-full items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap sm:w-auto">
                        <span aria-hidden="true" class="mr-1">👥</span>
                        <span>Clients</span>
                    </a>
                    <a href="{{ route('invoices.index') }}" class="inline-flex w-full items-center justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap sm:w-auto">
                        <span aria-hidden="true" class="mr-1">📄</span>
                        <span>Invoices</span>
                    </a>
                </div>
            </div>
        </div>
    </x-slot>

    @php
        $counts = $snapshot['counts'] ?? [];
        $totals = $snapshot['totals'] ?? [];
        $actionItems = $snapshot['action_items'] ?? [];
        $receiptReviewItems = $actionItems['receipt_reviews'] ?? [];
        $recent = $snapshot['recent_payments'] ?? [];
    @endphp

    <style>
        @media (max-width: 767px) {
            .payment-type-col { display: none; }
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-800" role="status" aria-live="polite">
                    {{ session('status') }}
                </div>
            @endif

            @if (!empty($showGettingStartedPrompt))
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-400/25 dark:bg-indigo-950/35"
                     style="border-color: currentColor;"
                     data-getting-started-prompt>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-indigo-700 dark:text-indigo-200">Getting Started</p>
                            <h3 class="mt-1 text-base font-semibold text-indigo-950 dark:text-indigo-100">Finish your setup flow</h3>
                            <p class="mt-1 text-sm text-indigo-900 dark:text-indigo-200">Connect a wallet, create an invoice, and send your first share-enabled invoice.</p>
                        </div>
                        <button type="button"
                                data-getting-started-temp-hide
                                aria-label="Hide prompt until reload"
                                title="Hide for now (until reload)"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-indigo-200 bg-white/80 text-sm font-semibold text-indigo-700 hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/35 dark:bg-slate-900/60 dark:text-indigo-200 dark:hover:bg-slate-900">
                            ×
                        </button>
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ $gettingStartedUrl }}"
                           class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-white dark:text-indigo-900 dark:hover:bg-indigo-50 dark:focus:ring-offset-slate-900">
                            Resume getting started
                        </a>
                        <form method="POST"
                              action="{{ route('getting-started.dismiss') }}"
                              onsubmit="return confirm('Hide getting started for now? You can reopen it later from the account menu.');">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center justify-center rounded-md border border-indigo-300 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/35 dark:bg-slate-900/70 dark:text-indigo-200 dark:hover:bg-indigo-950/50 dark:focus:ring-offset-slate-900">
                                Hide for now
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if (!empty($receiptReviewItems))
                <section class="rounded-xl border border-amber-200 bg-amber-50/90 p-5 shadow-sm dark:border-amber-400/35 dark:bg-amber-950/30"
                         style="border-color: currentColor;"
                         data-dashboard-action-items>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-amber-800 dark:text-amber-200">Action items</p>
                            <h3 class="mt-1 text-base font-semibold text-amber-950 dark:text-amber-50">Receipts waiting for review</h3>
                            <p class="mt-1 text-sm text-amber-900 dark:text-amber-100">
                                Review and send the client receipts that are still waiting on your action.
                            </p>
                        </div>
                        <div class="inline-flex items-center self-start rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-amber-900 shadow-sm ring-1 ring-amber-200 dark:bg-slate-900/70 dark:text-amber-100 dark:ring-amber-400/30">
                            {{ count($receiptReviewItems) }} pending
                        </div>
                    </div>
                    <div class="mt-4 space-y-3">
                        @foreach ($receiptReviewItems as $receiptReview)
                            <div class="flex flex-col gap-3 rounded-lg border border-amber-200/80 bg-white/80 px-4 py-3 shadow-sm dark:border-amber-400/25 dark:bg-slate-900/70 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span aria-hidden="true" class="inline-flex h-2.5 w-2.5 rounded-full bg-red-500"></span>
                                        <a href="{{ route('invoices.show', $receiptReview['invoice_id']) }}"
                                           class="text-sm font-semibold text-indigo-700 hover:text-indigo-600 dark:text-indigo-300 dark:hover:text-indigo-200">
                                            {{ $receiptReview['invoice_number'] }}
                                        </a>
                                        <span class="text-sm text-amber-900 dark:text-amber-100">
                                            {{ $receiptReview['client_name'] ?? 'Client' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-amber-900 dark:text-amber-100">
                                        {{ $receiptReview['summary'] }}
                                    </p>
                                </div>
                                <a href="{{ route('invoices.show', $receiptReview['invoice_id']) }}#receipt-review-panel"
                                   data-review-receipt-link="true"
                                   class="inline-flex items-center justify-center self-start rounded-md bg-amber-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400 dark:focus:ring-offset-slate-900">
                                    Review receipt
                                </a>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="grid gap-4"
                 style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));">
                <div class="bg-white shadow-sm sm:rounded-lg p-2 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Outstanding (USD)</p>
                            <div class="text-3xl font-bold text-gray-900">${{ number_format($totals['outstanding_usd'] ?? 0, 2) }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-indigo-700">💰</span>
                            <span class="text-xs font-semibold text-gray-900">Open total</span>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">BTC: {{ ($totals['outstanding_btc'] ?? 0) > 0 ? ($totals['outstanding_btc'] ?? 0) . ' BTC' : '—' }}</p>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-2 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Open invoices</p>
                            <div class="text-3xl font-bold text-gray-900">{{ $counts['open'] ?? 0 }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-700">📄</span>
                            <div class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-semibold text-center">Draft/Sent/Partial</div>
                        </div>
                    </div>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-600">
                        <div>
                            <dt class="text-gray-500">Draft</dt>
                            <dd class="font-semibold text-gray-900">{{ $counts['draft'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Sent</dt>
                            <dd class="font-semibold text-gray-900">{{ $counts['sent'] ?? 0 }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Partial</dt>
                            <dd class="font-semibold text-gray-900">{{ $counts['partial'] ?? 0 }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-2 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Past due</p>
                            <div class="text-3xl font-bold text-gray-900">{{ $counts['past_due'] ?? 0 }}</div>
                        </div>
                        <div class="text-right">
                            <div class="flex items-center gap-2 justify-end">
                                <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-50 text-amber-800">⏰</span>
                                <div class="rounded-full bg-amber-50 text-amber-800 px-3 py-1 text-xs font-semibold text-center">Due &amp; open</div>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">${{ number_format($totals['past_due_usd'] ?? 0, 2) }}</div>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Invoices past their due date (excluding paid/void).</p>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-2 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Upcoming (7 days)</p>
                            <div class="text-3xl font-bold text-gray-900">{{ $counts['upcoming_due'] ?? 0 }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-sky-50 text-sky-700 dark:bg-sky-900 dark:text-sky-200">📅</span>
                            <div class="rounded-full bg-sky-50 text-sky-700 dark:bg-sky-900 dark:text-sky-200 px-3 py-1 text-xs font-semibold text-center">Due soon</div>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Invoices due in the next 7 days.</p>
                    <p class="text-sm text-gray-500">USD: ${{ number_format($totals['upcoming_due_usd'] ?? 0, 2) }}</p>
                </div>

                <div class="bg-white shadow-sm sm:rounded-lg p-2 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Payments (last 7 days)</p>
                            <div class="text-3xl font-bold text-gray-900">${{ number_format($totals['payments_last_7d_usd'] ?? 0, 2) }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100">✅</span>
                            <div class="rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100 px-3 py-1 text-xs font-semibold text-center">{{ $counts['payments_last_7d'] ?? 0 }} payments</div>
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-gray-500">Total detected in the last 7 days.</p>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg border border-gray-100 p-2 sm:p-3 mt-4">
                <div class="pb-2 sm:pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Recent payments</h3>
                        <p class="text-sm text-gray-500">Latest 5 payments detected across your invoices.</p>
                    </div>
                    <a href="{{ route('invoices.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">View invoices</a>
                </div>

                @if (empty($recent))
                    <div class="py-2 text-sm text-gray-500">
                        No payments yet. Create and send an invoice to start tracking payments.
                    </div>
                @else
                    <div class="pt-2 flex justify-center">
                        <div class="overflow-x-auto max-w-full">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                                        <th class="py-2 px-2">Invoice</th>
                                        <th class="py-2 px-2">Client</th>
                                        <th class="py-2 px-2 text-right">Amount (USD)</th>
                                        <th class="py-2 px-2 payment-type-col">Type</th>
                                        <th class="py-2 px-2">Detected at</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($recent as $payment)
                                        <tr class="text-gray-900">
                                            <td class="py-2 px-2">
                                                @if(!empty($payment['invoice_id']))
                                                    <a href="{{ route('invoices.show', $payment['invoice_id']) }}" class="text-indigo-600 hover:text-indigo-500 font-semibold">
                                                        {{ $payment['invoice_number'] ?? 'Invoice' }}
                                                    </a>
                                                    @if (!empty($payment['needs_receipt_review']) && !empty($payment['payment_id']))
                                                        <div class="mt-1">
                                                            <a href="{{ route('invoices.show', $payment['invoice_id']) }}#receipt-review-panel"
                                                               data-review-receipt-link="true"
                                                               class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                                                <span aria-hidden="true" class="mr-1.5 inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                                                Review receipt
                                                            </a>
                                                        </div>
                                                    @endif
                                                @else
                                                    {{ $payment['invoice_number'] ?? 'Invoice' }}
                                                @endif
                                            </td>
                                            <td class="py-2 px-2 text-gray-700">{{ $payment['client_name'] ?? '—' }}</td>
                                            <td class="py-2 px-2 text-right font-semibold">${{ number_format($payment['amount_usd'] ?? 0, 2) }}</td>
                                            <td class="py-2 px-2 payment-type-col text-gray-700">
                                                @if (isset($payment['is_partial']))
                                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                                        {{ $payment['is_partial'] ? 'Partial' : 'Full' }}
                                                    </span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="py-2 px-2 text-gray-700">
                                                @php
                                                    $detected = $payment['detected_at'] ?? null;
                                                    $detectedIso = $detected?->copy()->utc()->toIso8601String();
                                                @endphp
                                                @if ($detectedIso)
                                                    <time data-utc-ts="{{ $detectedIso }}" datetime="{{ $detectedIso }}">
                                                        {{ $detected->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }}
                                                    </time>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const prompt = document.querySelector('[data-getting-started-prompt]');
            const hidePromptButton = document.querySelector('[data-getting-started-temp-hide]');
            if (prompt && hidePromptButton) {
                hidePromptButton.addEventListener('click', () => {
                    prompt.classList.add('hidden');
                });
            }

            document.querySelectorAll('[data-utc-ts]').forEach((node) => {
                const iso = node.getAttribute('data-utc-ts');
                if (!iso) return;

                const parsed = new Date(iso);
                if (Number.isNaN(parsed.getTime())) return;

                const localized = parsed.toLocaleString(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                });

                if (localized) node.textContent = localized;
            });
        });
    </script>
</x-app-layout>
