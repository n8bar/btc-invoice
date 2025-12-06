<x-emoji-favicon symbol="📊" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Dashboard') }}
                </h2>
                <p class="text-sm text-gray-500">At-a-glance invoice and payment health.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('clients.index') }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap shrink-0">
                    Clients
                </a>
                <a href="{{ route('clients.create') }}" class="inline-flex items-center rounded-md bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm ring-1 ring-indigo-200 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap shrink-0">
                    New client
                </a>
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap shrink-0">
                    Invoices
                </a>
                <a href="{{ route('invoices.create') }}" class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 whitespace-nowrap shrink-0">
                    Create invoice
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $counts = $snapshot['counts'] ?? [];
        $totals = $snapshot['totals'] ?? [];
        $recent = $snapshot['recent_payments'] ?? [];
    @endphp

    <style>
        @media (max-width: 767px) {
            .payment-type-col { display: none; }
        }
    </style>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
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
                            <div class="rounded-full bg-blue-50 text-blue-700 px-3 py-1 text-xs font-semibold">Draft/Sent/Partial</div>
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
                                <div class="rounded-full bg-amber-50 text-amber-800 px-3 py-1 text-xs font-semibold">Due &amp; open</div>
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
                            <div class="rounded-full bg-sky-50 text-sky-700 dark:bg-sky-900 dark:text-sky-200 px-3 py-1 text-xs font-semibold">Due soon</div>
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
                            <div class="rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-100 px-3 py-1 text-xs font-semibold">{{ $counts['payments_last_7d'] ?? 0 }} payments</div>
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
