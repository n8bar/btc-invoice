<x-app-layout>
    <x-slot name="header">
        @php $st = $invoice->status ?? 'draft'; @endphp
        <h2 class="text-xl font-semibold leading-tight">
            Invoice <span class="text-gray-500">#{{ $invoice->number }}</span>
            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
      @switch($st)
        @case('paid') bg-green-100 text-green-800 @break
        @case('sent') bg-blue-100 text-blue-800 @break
        @case('void') bg-yellow-100 text-yellow-800 @break
        @default bg-gray-100 text-gray-800
      @endswitch">
      {{ strtoupper($st) }}
    </span>
        </h2>
    </x-slot>


    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

            <div class="flex items-center justify-between">
                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:underline">← Back to Invoices</a>
                @php
                    $st = $invoice->status ?? 'draft';
                    $canMarkSent = !in_array($st, ['sent','paid','void']);
                    $canMarkPaid = !in_array($st, ['paid','void']);
                    $canVoid     = $st !== 'void';
                @endphp

                <div class="flex items-center gap-2">
                    {{-- Mark sent --}}
                    <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'sent']) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-secondary-button type="submit" :disabled="!$canMarkSent">
                            Mark sent
                        </x-secondary-button>
                    </form>

                    {{-- Mark paid --}}
                    <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'paid']) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-secondary-button type="submit" :disabled="!$canMarkPaid">
                            Mark paid
                        </x-secondary-button>
                    </form>

                    {{-- Void --}}
                    <form method="POST"
                          action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'void']) }}"
                          class="inline"
                          onsubmit="return confirm('Void invoice {{ $invoice->number }}? ');">
                        @csrf @method('PATCH')
                        <x-danger-button type="submit" :disabled="!$canVoid">Void</x-danger-button>
                    </form>

                    {{-- Reset to draft (undo) --}}
                    @if ($st !== 'draft')
                        <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'draft']) }}" class="inline">
                            @csrf @method('PATCH')
                            <x-secondary-button type="submit" >Reset to draft</x-secondary-button>
                        </form>
                    @endif
                </div>

            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="grid grid-cols-1 gap-0 md:grid-cols-2">
                    <div class="p-6 border-b md:border-b-0 md:border-r">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Summary</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">Client</dt><dd>{{ $invoice->client->name ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Status</dt><dd class="uppercase">{{ $invoice->status ?? 'draft' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Due date</dt><dd>{{ optional($invoice->due_date)->toDateString() ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Paid at</dt><dd>{{ optional($invoice->paid_at)->toDateTimeString() ?: '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="p-6">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Amounts</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">USD</dt><dd>${{ number_format($invoice->amount_usd, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC rate (USD/BTC)</dt><dd>{{ $invoice->btc_rate ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC</dt><dd>{{ $invoice->amount_btc ?? '—' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="p-6 border-t">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">BTC address</dt><dd class="font-mono">{{ $invoice->btc_address ?: '—' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">TXID</dt><dd class="font-mono">{{ $invoice->txid ?: '—' }}</dd></div>
                    </dl>
                </div>

                <div class="p-6 border-t">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Description</h3>
                    <p class="text-sm text-gray-800 whitespace-pre-line">{{ $invoice->description ?: '—' }}</p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

