{{-- resources/views/clients/index.blade.php --}}
<x-emoji-favicon symbol="👥" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Clients</h2>
                <p class="text-sm text-gray-500">Manage the people and businesses you invoice.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('dashboard') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:hidden">
                    Dashboard
                </a>
                <a href="{{ route('clients.trash') }}"
                   class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Trash
                </a>
                <a href="{{ route('clients.create') }}">
                    <x-primary-button>New client</x-primary-button>
                </a>
            </div>
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
                                <th class="hidden px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 md:table-cell">Notes</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($clients as $client)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        <a href="{{ route('clients.show', $client) }}" class="text-indigo-600 hover:underline">
                                            {{ $client->name }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if ($client->email)
                                            <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:underline">
                                                {{ $client->email }}
                                            </a>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="hidden px-6 py-4 text-sm text-gray-700 md:table-cell">
                                        {{ \Illuminate\Support\Str::limit($client->notes ?? '', 120) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <div class="flex flex-col items-stretch gap-1 sm:flex-row sm:items-center sm:justify-end sm:gap-2">
                                            <a href="{{ route('clients.edit', $client) }}"
                                               class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-3 py-1.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-50 sm:w-auto">
                                                Edit
                                            </a>
                                            <form action="{{ route('clients.destroy', $client) }}" method="POST" class="w-full sm:w-auto">
                                                @csrf
                                                @method('DELETE')
                                                <x-danger-button type="submit"
                                                                 class="w-full justify-center text-center sm:w-auto"
                                                                 onclick="return confirm('Delete client {{ $client->name }}? This moves them to trash.');">
                                                    Delete
                                                </x-danger-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                        <p>No clients yet. Create your first client to start sending invoices.</p>
                                        <a href="{{ route('clients.create') }}"
                                           class="mt-3 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white hover:bg-gray-700">
                                            Create client
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
