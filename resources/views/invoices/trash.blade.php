<x-emoji-favicon symbol="🗑️" bg="#FFE4E6" />
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight">Invoices — Trash</h2>
                <p class="text-sm text-gray-500">Restore deleted invoices or permanently remove them.</p>
            </div>
            <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:underline">Back to invoices</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deleted</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($invoices as $inv)
                        <tr>
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $inv->number }}</td>
                            <td class="px-6 py-3 text-sm">{{ $inv->client->name ?? '—' }}</td>
                            <td class="px-6 py-3 text-sm text-gray-700">{{ optional($inv->deleted_at)->diffForHumans() }}</td>
                            <td class="px-6 py-3 text-sm text-right">
                                <form action="{{ route('invoices.restore', $inv->id) }}" method="POST" class="inline">
                                    @csrf @method('PATCH')
                                    <x-secondary-button type="submit" class="mr-2">Restore</x-secondary-button>
                                </form>
                                <form action="{{ route('invoices.force-destroy', $inv->id) }}" method="POST" class="inline"
                                      onsubmit="return confirm('Permanently delete invoice {{ $inv->number }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <x-danger-button type="submit">Delete forever</x-danger-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                <p>Trash is empty.</p>
                                <a href="{{ route('invoices.index') }}" class="mt-3 inline-flex items-center text-indigo-600 hover:underline">
                                    Return to invoices
                                </a>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $invoices->onEachSide(1)->links() }}</div>
        </div>
    </div>
</x-app-layout>
