<x-emoji-favicon symbol="🧾" bg="#E0E7FF" />
<x-app-layout>
  <x-slot name="header">
      <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
              <h2 class="text-xl font-semibold leading-tight">Invoices</h2>
              <p class="text-sm text-gray-500">Create, send, and track invoice payment status.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
              <a href="{{ route('dashboard') }}"
                 class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:hidden">
                  Dashboard
              </a>
              <a href="{{ route('invoices.trash') }}"
                 class="inline-flex items-center rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                  Trash
              </a>
              <a href="{{ route('invoices.create') }}"><x-primary-button>New invoice</x-primary-button></a>
          </div>
      </div>
  </x-slot>

  <div class="py-8">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
@if (session('status'))
    <div class="mb-4 rounded-md bg-green-50 p-3 text-green-700">{{ session('status') }}</div>
@endif

@php $showIdColumn = $showInvoiceIds ?? false; @endphp

<div class="rounded-lg bg-white shadow">
    <div class="relative" data-scroll-fade-wrapper>
        <div class="overflow-x-auto" data-scroll-fade-container>
            @isset($invoices)
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        @if ($showIdColumn)
                            <th class="hidden px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider md:table-cell">ID</th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Number</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="hidden px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider md:table-cell">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse ($invoices as $inv)
                        @php
                            $amountUsdDisplay = $inv->amount_usd !== null
                                ? '$' . number_format((float) $inv->amount_usd, 2)
                                : '—';
                            $amountBtcDisplay = $inv->amount_btc !== null
                                ? rtrim(rtrim(number_format((float) $inv->amount_btc, 5, '.', ''), '0'), '.') . ' BTC'
                                : '—';
                        @endphp
                        <tr>
                        @if ($showIdColumn)
                            <td class="hidden px-6 py-3 text-sm text-gray-700 md:table-cell">{{ $inv->id }}</td>
                        @endif
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">
                                <a href="{{ route('invoices.show', $inv) }}" class="text-indigo-600 hover:underline">{{ $inv->number }}</a>
                                <div class="mt-1 text-xs font-normal text-gray-500 md:hidden">
                                    {{ $amountUsdDisplay }} / {{ $amountBtcDisplay }}
                                </div>
                            </td>
                            <td class="px-6 py-3 text-sm">{{ $inv->client->name ?? '—' }}</td>
                            <td class="hidden px-6 py-3 text-sm md:table-cell">
                                <div class="flex flex-col">
                                    <span>{{ $amountUsdDisplay }}</span>
                                    <span class="text-xs text-gray-500">{{ $amountBtcDisplay }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-sm">{{ optional($inv->due_date)->toDateString() ?: '—' }}</td>
                            <td class="px-6 py-3 text-sm">{{ $inv->status ?? 'draft' }}</td>
                            <td class="px-6 py-3 text-sm align-middle">
                                <div class="flex flex-col items-stretch gap-1 sm:flex-row sm:items-center sm:justify-end sm:gap-2">
                                    <a href="{{ route('invoices.edit', $inv) }}"
                                       class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-3 py-1.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-700 hover:bg-gray-50 sm:w-auto">
                                        Edit
                                    </a>
                                    <form action="{{ route('invoices.destroy', $inv) }}" method="POST" class="w-full sm:w-auto"
                                          onsubmit="return confirm('Delete invoice {{ $inv->number }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-danger-button class="w-full justify-center text-center sm:w-auto">Delete</x-danger-button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                    @empty
                        @php $emptyColspan = $showIdColumn ? 8 : 7; @endphp
                        <tr>
                            <td colspan="{{ $emptyColspan }}" class="px-6 py-10 text-center text-sm text-gray-500">
                                <p>No invoices yet. Create one to generate a payment address and share link.</p>
                                <a href="{{ route('invoices.create') }}"
                                   class="mt-3 inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-wider text-white hover:bg-gray-700">
                                    Create invoice
                                </a>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            @else
                <div class="p-6 text-sm text-gray-600">Controller not wired yet. We’ll do that next.</div>
            @endisset
        </div>
        <div aria-hidden="true"
             data-scroll-fade
             class="pointer-events-none absolute inset-y-0 right-0 z-10 hidden w-20 bg-gradient-to-l from-gray-100/95 via-white/70 to-transparent md:hidden dark:from-slate-900 dark:via-slate-900/55"></div>
    </div>
</div>

@isset($invoices)
    <div class="mt-4">{{ $invoices->onEachSide(1)->links() }}</div>
    @endisset
    </div>
    </div>
    </x-app-layout>
