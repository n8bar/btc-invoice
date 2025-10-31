<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight">Edit Invoice</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">

            @if($invoice->public_enabled)
                <div class="mb-4 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800">
                    This invoice is currently public. To edit, first
                    <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST" class="inline"
                          onsubmit="return confirm('Disable the public link?');">
                        @csrf @method('PATCH')
                        <button type="submit" class="underline text-red-600 hover:text-red-700">disable the public link</button>
                    </form>.
                </div>
            @endif

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
                        <input type="number" step="0.01" min="0" name="amount_usd" id="amount_usd" value="{{ old('amount_usd',$invoice->amount_usd) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('amount_usd')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">BTC rate (USD/BTC)</label>
                        <input type="number" step="0.01" min="0" name="btc_rate" id="btc_rate" value="{{ old('btc_rate',$invoice->btc_rate) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('btc_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="button" id="useCurrentRate"
                            class="mt-2 inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Use current rate
                    </button>
                    <small id="rateStamp" class="ml-2 text-xs text-gray-500"></small>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount (BTC)</label>
                        <input type="number" step="0.00000001" min="0" name="amount_btc" id="amount_btc" value="{{ old('amount_btc',$invoice->amount_btc) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        <p class="mt-1 text-xs text-gray-500">Amounts auto-calculate as you type. Use “Use current rate” to refresh.</p>
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

    <script>
        (function(){
            const btn = document.getElementById('useCurrentRate');
            const rateInput = document.getElementById('btc_rate');
            const usdInput  = document.getElementById('amount_usd');
            const btcInput  = document.getElementById('amount_btc');
            const stampEl   = document.getElementById('rateStamp');
            const fallbackTz = @json(config('app.timezone'));

            const formatAsOf = (isoString, tz) => {
                if (!isoString) return '';
                try {
                    const zone = tz || fallbackTz;
                    const formatter = new Intl.DateTimeFormat(undefined, {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false,
                        timeZone: zone,
                        timeZoneName: 'short',
                    });
                    return `${formatter.format(new Date(isoString))} (${zone})`;
                } catch (e) {
                    return new Date(isoString).toLocaleString();
                }
            };

            async function fetchRate(){
                try{
                    btn.disabled = true; btn.textContent = 'Updating…';
                    const res = await fetch('{{ route('invoices.rate') }}', { headers:{Accept:'application/json'}});
                    const data = await res.json();
                    if(!res.ok || !data.ok) throw new Error(data.message || 'Rate unavailable');

                    rateInput.value = Number(data.rate_usd).toFixed(2);
                    const usd = parseFloat(usdInput.value);
                    if(!isNaN(usd) && usd>0){
                        const r = parseFloat(rateInput.value);
                        if(r>0) btcInput.value = (usd/r).toFixed(8);
                    }
                    if(data.as_of){
                        stampEl.textContent = `as of ${formatAsOf(data.as_of, data.timezone)}`;
                    }
                }catch(e){ alert(e.message || 'Could not fetch rate.'); }
                finally{ btn.disabled = false; btn.textContent = 'Use current rate'; }
            }
            btn?.addEventListener('click', fetchRate);
        })();


        (() => {
            const usd  = document.getElementById('amount_usd');
            const rate = document.getElementById('btc_rate');
            const btc  = document.getElementById('amount_btc');

            let active = null; // 'usd' | 'btc' | 'rate'
            const parse = el => { const v = parseFloat(el.value); return Number.isFinite(v) ? v : null; };

            const recalc = () => {
                const r = parse(rate);
                if (!r || r <= 0) return;

                if (active === 'usd') {
                    const u = parse(usd); if (u != null) btc.value = (u / r).toFixed(8);
                } else if (active === 'btc') {
                    const b = parse(btc); if (b != null) usd.value = (b * r).toFixed(2);
                } else if (active === 'rate') {
                    const u = parse(usd), b = parse(btc);
                    if (u != null) btc.value = (u / r).toFixed(8);
                    else if (b != null) usd.value = (b * r).toFixed(2);
                }
            };

            ['input','change'].forEach(ev => {
                usd .addEventListener(ev, () => { active = 'usd';  recalc(); });
                btc .addEventListener(ev, () => { active = 'btc';  recalc(); });
                rate.addEventListener(ev, () => { active = 'rate'; recalc(); });
            });
        })();
    </script>

</x-app-layout>
