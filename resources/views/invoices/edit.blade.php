<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight">Edit Invoice</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('invoices.update', $invoice) }}" class="space-y-6">
                @csrf @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700">Client</label>
                    <select name="client_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id',$invoice->client_id)==$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Number</label>
                        <input name="number" value="{{ old('number',$invoice->number) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Invoice date</label>
                        <input type="date" name="invoice_date"
                               value="{{ old('invoice_date', optional($invoice->invoice_date)->toDateString() ?: now()->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('invoice_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Due date</label>
                        <input type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->toDateString()) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description',$invoice->description) }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount (USD)</label>
                        <input type="number" step="0.01" min="0" name="amount_usd" value="{{ old('amount_usd',$invoice->amount_usd) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('amount_usd')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">BTC rate (USD/BTC)</label>
                        <input type="number" step="0.01" min="0" name="btc_rate" value="{{ old('btc_rate',$invoice->btc_rate) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('btc_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount (BTC)</label>
                        <input type="number" step="0.00000001" min="0" name="amount_btc" value="{{ old('amount_btc',$invoice->amount_btc) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('amount_btc')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">BTC address</label>
                        <input name="btc_address" value="{{ old('btc_address',$invoice->btc_address) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('btc_address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['draft','sent','paid','void'] as $st)
                                <option value="{{ $st }}" @selected(old('status',$invoice->status ?? 'draft')===$st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">TXID (optional)</label>
                    <input name="txid" value="{{ old('txid',$invoice->txid) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                    @error('txid')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('invoices.index') }}" class="text-gray-600 hover:underline">Cancel</a>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
