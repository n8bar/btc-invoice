<x-emoji-favicon symbol="🧩" bg="#E0F2F1" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Support Client Detail</h2>
                <p class="text-sm text-gray-500">{{ $owner->name }} · read-only support access · expires {{ $supportAccessExpiresAt?->setTimezone(config('app.timezone'))->toDayDateTimeString() }}</p>
            </div>
            <a href="{{ route('support.owners.clients.index', $owner) }}" class="text-sm text-gray-600 hover:underline dark:text-slate-300">Back to support clients</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-400/40 dark:bg-blue-950/30 dark:text-blue-100">
                Support is viewing this client in read-only mode.
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900/80">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Client</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ $client->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Email</p>
                        <p class="mt-1 text-gray-900 dark:text-white">{{ $client->email }}</p>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Notes</p>
                    <p class="mt-1 whitespace-pre-line text-gray-900 dark:text-white">{{ $client->notes ?: '—' }}</p>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-slate-900/80">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">Recent invoices for this client</h3>
                    <span class="text-sm text-gray-500 dark:text-slate-400">{{ $recentInvoices->count() }} shown</span>
                </div>

                @if ($recentInvoices->isEmpty())
                    <p class="mt-4 text-sm text-gray-500 dark:text-slate-400">No invoices found for this client.</p>
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                                    <th class="py-2 pr-4">Invoice</th>
                                    <th class="py-2 pr-4">Amount</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 pr-4">Due</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($recentInvoices as $invoice)
                                    <tr>
                                        <td class="py-2 pr-4"><a href="{{ route('support.owners.invoices.show', [$owner, $invoice]) }}" class="text-indigo-600 hover:underline dark:text-indigo-300">{{ $invoice->number }}</a></td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">${{ number_format((float) $invoice->amount_usd, 2) }}</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ $invoice->status ?? 'draft' }}</td>
                                        <td class="py-2 pr-4 text-gray-700 dark:text-slate-300">{{ optional($invoice->due_date)->toDateString() ?: '—' }}</td>
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
