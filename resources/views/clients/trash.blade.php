<x-emoji-favicon symbol="🧺" bg="#FFE4E6" />
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight">Clients - Trash</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-600">Recently deleted clients. You can restore or delete forever.</p>
                <a href="{{ route('clients.index') }}" class="text-indigo-600 hover:underline">Back to Clients</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
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
                                    <button class="mr-2 rounded-md border border-gray-300 px-3 py-1.5 hover:bg-gray-50">Restore</button>
                                </form>
                                <form action="{{ route('clients.force-destroy', $client->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Permanently delete {{ $client->name }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button class="rounded-md bg-red-600 px-3 py-1.5 text-white hover:bg-red-700">Delete forever</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                Trash is empty.
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
