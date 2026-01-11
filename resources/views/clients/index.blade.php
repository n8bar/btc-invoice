
{{-- resources/views/clients/index.blade.php --}}
<x-emoji-favicon symbol="👥" bg="#E0F2FE" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Clients</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-6 mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-600">Manage your billing clients.</p>
                <a href="{{ route('clients.trash') }}" class="text-gray-600 hover:underline">Trash</a>
                <a href="{{ route('clients.create') }}">
                    <x-primary-button>New Client</x-primary-button>
                </a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-0 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                        <th class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                        <th class="px-2 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                        <th class="px-0 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($clients as $client)
                        <tr>
                            <td class="whitespace-nowrap px-0 py-4 text-sm text-gray-900">
                                <a href="{{ route('clients.show', $client) }}" class="text-indigo-600 hover:underline">
                                    {{ $client->name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-2 py-4 text-sm">
                                @if ($client->email)
                                    <a href="mailto:{{ $client->email }}" class="text-indigo-600 hover:underline">
                                        {{ $client->email }}
                                    </a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-4 text-sm text-gray-700">
                                {{ \Illuminate\Support\Str::limit($client->notes ?? '', 120) }}
                            </td>
                            <td class="whitespace-nowrap px-0 py-4 text-right text-sm">
                                <div class="flex flex-nowrap justify-end items-center gap-2">
                                    <a href="{{ route('clients.edit', $client) }}"
                                       class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                        ✏️
                                    </a>
                                    <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="return confirm('Delete {{ $client->name }}?')"
                                                class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm text-white hover:bg-red-700">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                No clients yet.
                                <a href="{{ route('clients.create') }}" class="text-indigo-600 hover:underline">Create one</a>.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $clients->onEachSide(1)->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
