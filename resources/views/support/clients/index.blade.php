<x-emoji-favicon symbol="👥" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Support Client View</h2>
                <p class="text-sm text-gray-500">{{ $owner->name }} · read-only support access · expires {{ $supportAccessExpiresAt?->setTimezone(config('app.timezone'))->toDayDateTimeString() }}</p>
            </div>
            <a href="{{ route('support.dashboard') }}" class="text-sm text-gray-600 hover:underline dark:text-slate-300">Back to support dashboard</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-400/40 dark:bg-blue-950/30 dark:text-blue-100">
                Support is viewing {{ $owner->name }}'s clients in read-only mode.
            </div>

            <div class="rounded-lg bg-white shadow dark:bg-slate-900/80">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-slate-900/90">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-slate-900/80">
                            @forelse ($clients as $client)
                                <tr>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">{{ $client->name }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-slate-300">{{ $client->email }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-700 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($client->notes ?? '', 120) ?: '—' }}</td>
                                    <td class="px-6 py-4 text-right text-sm">
                                        <a href="{{ route('support.owners.clients.show', [$owner, $client]) }}" class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-50 dark:border-white/20 dark:text-slate-100 dark:hover:bg-white/10">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-slate-400">No clients found for this issuer.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $clients->onEachSide(1)->links() }}</div>
        </div>
    </div>
</x-app-layout>
