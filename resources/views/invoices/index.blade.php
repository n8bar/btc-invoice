<x-emoji-favicon symbol="🧾" bg="#E0E7FF" />
<x-app-layout>
  <x-slot name="header"><h2 class="text-xl font-semibold leading-tight">Invoices</h2></x-slot>

  <div class="py-8">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
@endif

<div class="mt-6 mb-4 flex items-center justify-between">
    <p class="text-sm text-gray-600">Manage your invoices.</p>
    <a href="{{ route('invoices.trash') }}" class="text-gray-600 hover:underline">Trash</a>
    <a href="{{ route('invoices.create') }}"><x-primary-button>New Invoice</x-primary-button></a>
</div>

@php $showIdColumn = $showInvoiceIds ?? false; @endphp

<div class="overflow-hidden rounded-lg bg-white shadow">
    @isset($invoices)
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                @if ($showIdColumn)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                @endif
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">USD</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BTC</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
            @forelse ($invoices as $inv)
                <tr>
                    @if ($showIdColumn)
                        <td class="px-6 py-3 text-sm text-gray-700">{{ $inv->id }}</td>
                    @endif
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                        <a href="{{ route('invoices.show', $inv) }}" class="text-indigo-600 hover:underline">{{ $inv->number }}</a>
                    </td>
                    <td class="px-6 py-3 text-sm">{{ $inv->client->name ?? '—' }}</td>
                    <td class="px-6 py-3 text-sm">
                        <div class="flex flex-col">
                            <span>${{ number_format($inv->amount_usd, 2) }}</span>
                            <span class="text-xs text-gray-500">{{ $inv->amount_btc ?? '—' }} BTC</span>
                        </div>
                    </td>
                    <td class="px-6 py-3 text-sm">{{ optional($inv->due_date)->toDateString() ?: '—' }}</td>
                    <td class="px-6 py-3 text-sm">{{ $inv->status ?? 'draft' }}</td>
                    <td class="px-6 py-3 text-sm align-middle">
                        <div class="flex justify-end">
                            <x-action-menu>
                                <a href="{{ route('invoices.edit', $inv) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Edit</a>
                                <form action="{{ route('invoices.destroy', $inv) }}" method="POST" class="block"
                                      onsubmit="return confirm('Delete invoice {{ $inv->number }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="w-full px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50">Delete</button>
                                </form>
                            </x-action-menu>
                        </div>
                    </td>

                </tr>
            @empty
                @php $emptyColspan = $showIdColumn ? 8 : 7; @endphp
                <tr><td colspan="{{ $emptyColspan }}" class="px-6 py-10 text-center text-sm text-gray-500">No invoices yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    @else
        <div class="p-6 text-sm text-gray-600">Controller not wired yet. We’ll do that next.</div>
    @endisset
</div>

@isset($invoices)
    <div class="mt-4">{{ $invoices->onEachSide(1)->links() }}</div>
    @endisset
    </div>
    </div>
    </x-app-layout>
