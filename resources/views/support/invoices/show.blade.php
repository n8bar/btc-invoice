<x-emoji-favicon symbol="📄" bg="#FDE68A" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Support Invoice Detail</h2>
                <p class="text-sm text-gray-500">{{ $owner->name }} · read-only support access · expires {{ $supportAccessExpiresAt?->setTimezone(config('app.timezone'))->toDayDateTimeString() }}</p>
            </div>
            <a href="{{ route('support.owners.invoices.index', $owner) }}" class="text-sm text-gray-600 hover:underline dark:text-slate-300">Back to support invoices</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-400/40 dark:bg-blue-950/30 dark:text-blue-100">
                Support is viewing this invoice in read-only mode.
            </div>

            @if ($invoice->unsupported_configuration_flagged)
                <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-900 dark:border-red-400/50 dark:bg-red-950/40 dark:text-red-100">
                    <p class="font-semibold">Unsupported invoice</p>
                    <p class="mt-1">{{ $invoice->unsupported_configuration_details ?: 'Automatic payment attribution may be unreliable for this invoice.' }}</p>
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">
                <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900/80">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Invoice</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $invoice->number }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Status</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ strtoupper($invoice->status ?? 'draft') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Client</p>
                            <p class="mt-1 text-gray-900 dark:text-white">{{ $invoice->client->name ?? '—' }}</p>
                            <p class="text-sm text-gray-500 dark:text-slate-400">{{ $invoice->client->email ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Dates</p>
                            <p class="mt-1 text-gray-900 dark:text-white">Issued {{ optional($invoice->invoice_date)->toDateString() ?: '—' }}</p>
                            <p class="text-sm text-gray-500 dark:text-slate-400">Due {{ optional($invoice->due_date)->toDateString() ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Amount</p>
                            <p class="mt-1 text-gray-900 dark:text-white">${{ number_format((float) $invoice->amount_usd, 2) }}</p>
                            <p class="text-sm text-gray-500 dark:text-slate-400">{{ $invoice->formatBitcoinAmount((float) $invoice->amount_btc) }} BTC</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Payment Address</p>
                            <p class="mt-1 break-all text-sm text-gray-900 dark:text-white">{{ $invoice->payment_address ?: '—' }}</p>
                        </div>
                    </div>

                    @if ($invoice->description)
                        <div class="mt-6">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Description</p>
                            <p class="mt-1 whitespace-pre-line text-gray-900 dark:text-white">{{ $invoice->description }}</p>
                        </div>
                    @endif
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900/80">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Issuer</p>
                    <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $owner->name }}</p>
                    <p class="text-sm text-gray-500 dark:text-slate-400">{{ $owner->email }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900/80">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Payment history</h3>
                    <span class="text-sm text-gray-500 dark:text-slate-400">{{ $paymentHistory->count() }} entries</span>
                </div>

                @if ($paymentHistory->isEmpty())
                    <p class="mt-4 text-sm text-gray-500 dark:text-slate-400">No payments recorded yet.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                                    <th class="py-2 pr-4">Detected</th>
                                    <th class="py-2 pr-4">Sats</th>
                                    <th class="py-2 pr-4">USD snapshot</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Txid</th>
                                    <th class="py-2 pr-4">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($paymentHistory as $payment)
                                    @php
                                        $isOutgoingReattribution = $payment->isReattributedOutFrom($invoice);
                                        $isInboundReattribution = $payment->isReattributedInto($invoice);
                                        $relatedSourceInvoice = $payment->sourceInvoice;
                                        $relatedDestinationInvoice = $payment->accountingInvoice;
                                    @endphp
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ $payment->detected_at?->setTimezone(config('app.timezone'))->toDayDateTimeString() ?? '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ number_format((int) $payment->sats_received) }}</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ $payment->fiat_amount !== null ? '$' . number_format((float) $payment->fiat_amount, 2) : '—' }}</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">
                                            @if ($payment->is_adjustment)
                                                {{ $payment->sats_received >= 0 ? 'Manual credit' : 'Manual debit' }}
                                            @elseif ($payment->isIgnored())
                                                <div class="space-y-1">
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">Ignored</span>
                                                    <div class="text-xs text-amber-800 dark:text-amber-200">{{ $payment->ignore_reason }}</div>
                                                </div>
                                            @elseif ($isOutgoingReattribution)
                                                <div class="space-y-1">
                                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-900">Reattributed out</span>
                                                    @if ($relatedDestinationInvoice)
                                                        <div class="text-xs text-sky-800 dark:text-sky-200">
                                                            Counting on
                                                            <a href="{{ route('support.owners.invoices.show', [$owner, $relatedDestinationInvoice]) }}" class="font-semibold underline">
                                                                {{ $relatedDestinationInvoice->number }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            @elseif ($isInboundReattribution)
                                                <div class="space-y-1">
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900">Reattributed in</span>
                                                    @if ($relatedSourceInvoice)
                                                        <div class="text-xs text-emerald-800 dark:text-emerald-200">
                                                            Detected on
                                                            <a href="{{ route('support.owners.invoices.show', [$owner, $relatedSourceInvoice]) }}" class="font-semibold underline">
                                                                {{ $relatedSourceInvoice->number }}
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                {{ $payment->confirmed_at ? 'Confirmed' : 'Pending' }}
                                            @endif
                                        </td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">@if ($payment->txid)<span class="break-all">{{ $payment->txid }}</span>@else—@endif</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ $payment->note ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
