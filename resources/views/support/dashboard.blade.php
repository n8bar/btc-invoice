<x-emoji-favicon symbol="🛟" bg="#DBEAFE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Support Dashboard</h2>
                <p class="text-sm text-gray-500">Read-only access to owners who have active support grants.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
            @endif

            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-400/40 dark:bg-blue-950/30 dark:text-blue-100">
                This surface is read-only. Support access depends on an active owner grant and expires automatically.
            </div>

            <div class="rounded-lg bg-white shadow dark:bg-slate-900/80">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-slate-900/90">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Owner</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Access expires</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Counts</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-slate-900/80">
                            @forelse ($owners as $owner)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $owner->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-slate-300">{{ $owner->email }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-slate-300">{{ $owner->support_access_expires_at?->setTimezone(config('app.timezone'))->toDayDateTimeString() }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-slate-300">{{ $owner->clients_count }} clients / {{ $owner->invoices_count }} invoices</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <a href="{{ route('support.owners.invoices.index', $owner) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-slate-100 dark:hover:bg-white/10">Invoices</a>
                                            <a href="{{ route('support.owners.clients.index', $owner) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-slate-100 dark:hover:bg-white/10">Clients</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-slate-400">No active support grants right now.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $owners->onEachSide(1)->links() }}</div>
        </div>
    </div>
</x-app-layout>
