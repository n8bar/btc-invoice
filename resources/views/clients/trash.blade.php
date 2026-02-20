<x-emoji-favicon symbol="🧺" bg="#FFE4E6" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Clients - Trash</h2>
                <p class="text-sm text-gray-500">Restore deleted clients or permanently remove them.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">Back to clients</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-lg bg-white shadow">
                <div class="relative" data-scroll-fade-wrapper>
                    <div class="overflow-x-auto" data-scroll-fade-container>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deleted</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($clients as $client)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">{{ $client->name }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($client->email)
                                            <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:underline">{{ $client->email }}</a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                        {{ optional($client->deleted_at)->diffForHumans() }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <form action="{{ route('clients.restore', $client->id) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <x-secondary-button type="submit" class="mr-2">Restore</x-secondary-button>
                                        </form>
                                        <form action="{{ route('clients.force-destroy', $client->id) }}" method="POST" class="inline"
                                              onsubmit="return confirm('Permanently delete {{ $client->name }}? This cannot be undone.');">
                                            @csrf @method('DELETE')
                                            <x-danger-button type="submit">Delete forever</x-danger-button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                        <p>Trash is empty.</p>
                                        <a href="{{ route('clients.index') }}" class="mt-3 inline-flex items-center text-indigo-600 hover:underline">
                                            Return to clients
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div aria-hidden="true"
                         data-scroll-fade
                         class="pointer-events-none absolute inset-y-0 right-0 z-10 hidden w-20 bg-gradient-to-l from-gray-100/95 via-white/70 to-transparent md:hidden dark:from-slate-900 dark:via-slate-900/55"></div>
                </div>
            </div>

            <div class="mt-4">
                {{ $clients->onEachSide(1)->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
