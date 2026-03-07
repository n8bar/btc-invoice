<x-emoji-favicon symbol="✍️" bg="#FCE7F3" />
<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight">New Invoice</h2></x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
            @isset($gettingStartedStrip)
                @include('getting-started.partials.progress-strip', ['strip' => $gettingStartedStrip])
            @endisset
            @php
                $isGettingStartedContext = request()->boolean('getting_started');
                $onboardingGlow = 'ring-2 ring-indigo-300 ring-offset-2 ring-offset-white dark:ring-indigo-400/70 dark:ring-offset-slate-900';
                $gettingStartedMarker = '👉';
            @endphp

            @if ($showClientGate ?? false)
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Create your first client</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Invoices need a client first. Add one now, then we will continue to invoice creation.
                    </p>

                    <form method="POST" action="{{ route('clients.store') }}" class="mt-5 space-y-5">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ $clientGateReturnTo }}">
                        @include('clients.partials.form-fields', ['showNotes' => false])

                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('clients.index') }}" class="text-sm text-gray-600 hover:underline">Manage clients</a>
                            <x-primary-button
                                class="{{ $isGettingStartedContext ? $onboardingGlow : '' }}"
                                :data-getting-started-highlight="$isGettingStartedContext ? 'invoice-create-client' : null">
                                {{ $isGettingStartedContext ? $gettingStartedMarker . ' Create client' : 'Create client' }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('invoices.store') }}" class="space-y-6">
                @csrf
                @if ($isGettingStartedContext)
                    <input type="hidden" name="getting_started" value="1">
                @endif
                @php
                    $invoiceDefaults = $invoiceDefaults ?? ['description'=>null,'due_date'=>null,'terms_days'=>null];
                @endphp

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Client <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <select name="client_id" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select…</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id')==$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Number</label>
                        <input name="number"
                               {{--  value="{{ old('number','INV-'.now()->format('Ymd-His')) }}" required  --}}
                               value="{{ old('number') }}"
                               placeholder="{{ $suggestedNumber }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Invoice date <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', $today) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('invoice_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Due date</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $invoiceDefaults['due_date'] ?? null) }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('due_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        @if (!empty($invoiceDefaults['terms_days']))
                            <p class="mt-1 text-xs text-gray-500">
                                Defaults to {{ $invoiceDefaults['terms_days'] }} days after the invoice date.
                            </p>
                        @endif
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $invoiceDefaults['description'] ?? null) }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Amount (USD) <span class="text-red-600" aria-hidden="true">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" name="amount_usd" id="amount_usd" value="{{ old('amount_usd') }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('amount_usd')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            BTC rate (USD/BTC)
                        </label>
                        <input type="number" step="0.01" min="0" name="btc_rate" id="btc_rate"
                               value="{{ old('btc_rate', $prefillRate) }}" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        @error('btc_rate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <p class="mt-1 text-xs text-gray-500">This rate is just for display—each payment uses the USD/BTC rate captured at the moment funds arrive.</p>
                    <button type="button"
                            id="useCurrentRate"
                            class="mt-2 inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-center text-sm font-semibold leading-5 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60">
                        Use current rate
                    </button>
                    <small id="rateStamp" class="ml-2 text-xs text-gray-500"></small>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Amount (BTC)</label>
                        <input type="number" step="0.00000001" min="0" name="amount_btc" id="amount_btc" value="{{ old('amount_btc') }}"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"/>
                        <p class="mt-1 text-xs text-gray-500">Amounts auto-calculate as you type. Use “Use current rate” to refresh.</p>
                        @error('amount_btc')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="rounded border border-indigo-100 bg-indigo-50 p-3 text-sm text-indigo-900" style="border-color: currentColor;">
                    Payment addresses are generated from your wallet settings for every invoice.
                    <a href="{{ route('wallet.settings.edit') }}" class="underline">Manage wallet settings</a>.
                </div>

                @php
                    $brand = $brandingDefaults ?? ['heading'=>null,'name'=>null,'email'=>null,'phone'=>null,'address'=>null,'footer_note'=>null];
                    $brandingFieldNames = [
                        'branding_heading_override','billing_name_override','billing_email_override','billing_phone_override',
                        'billing_address_override','invoice_footer_note_override',
                    ];
                    $brandingHasErrors = collect($brandingFieldNames)->contains(fn ($field) => $errors->has($field));
                    $brandingHasInput = collect($brandingFieldNames)->contains(fn ($field) => filled(old($field)));
                    $brandingOpen = $brandingHasErrors || $brandingHasInput;
                @endphp
                <details class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm" @if($brandingOpen) open @endif>
                    <summary class="flex cursor-pointer items-center justify-between text-sm font-semibold text-gray-700">
                        <span>Branding &amp; footer</span>
                        <span class="text-xs font-normal text-gray-500">Leave fields blank to use profile defaults.</span>
                    </summary>
                    <div class="mt-4 space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="branding_heading_override" :value="__('Invoice heading')" />
                                <x-text-input id="branding_heading_override" name="branding_heading_override" type="text"
                                              class="mt-1 block w-full"
                                              :value="old('branding_heading_override')"
                                              placeholder="{{ $brand['heading'] ?? 'Invoice' }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('branding_heading_override')" />
                            </div>
                            <div>
                                <x-input-label for="billing_name_override" :value="__('Biller name')" />
                                <x-text-input id="billing_name_override" name="billing_name_override" type="text"
                                              class="mt-1 block w-full"
                                              :value="old('billing_name_override')"
                                              placeholder="{{ $brand['name'] ?? 'Biller name' }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('billing_name_override')" />
                            </div>
                            <div>
                                <x-input-label for="billing_email_override" :value="__('Biller email')" />
                                <x-text-input id="billing_email_override" name="billing_email_override" type="email"
                                              class="mt-1 block w-full"
                                              :value="old('billing_email_override')"
                                              placeholder="{{ $brand['email'] ?? 'name@example.com' }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('billing_email_override')" />
                            </div>
                            <div>
                                <x-input-label for="billing_phone_override" :value="__('Biller phone')" />
                                <x-text-input id="billing_phone_override" name="billing_phone_override" type="text"
                                              class="mt-1 block w-full"
                                              :value="old('billing_phone_override')"
                                              placeholder="{{ $brand['phone'] ?? '(555) 123-4567' }}" />
                                <x-input-error class="mt-2" :messages="$errors->get('billing_phone_override')" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="billing_address_override" :value="__('Biller address')" />
                            <textarea id="billing_address_override" name="billing_address_override" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="{{ $brand['address'] ?? '123 Main St, Suite 100, Denver, CO 80202' }}">{{ old('billing_address_override') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('billing_address_override')" />
                        </div>
                        <div>
                            <x-input-label for="invoice_footer_note_override" :value="__('Footer note (public & print)')" />
                            <textarea id="invoice_footer_note_override" name="invoice_footer_note_override" rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                      placeholder="{{ $brand['footer_note'] ?? 'We appreciate your business.' }}">{{ old('invoice_footer_note_override') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('invoice_footer_note_override')" />
                        </div>
                    </div>
                </details>
                <p class="text-xs text-gray-600">
                    Need to update your default branding or footer note?
                    <a href="{{ route('settings.invoice.edit') }}" class="font-medium text-indigo-600 hover:text-indigo-500 hover:underline">
                        Open Invoice Settings
                    </a>.
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach (['draft','sent','paid','void'] as $st)
                                <option value="{{ $st }}" @selected(old('status','draft')===$st)>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('invoices.index') }}" class="text-gray-600 hover:underline">Cancel</a>
                    <x-primary-button
                        class="{{ $isGettingStartedContext ? $onboardingGlow : '' }}"
                        :data-getting-started-highlight="$isGettingStartedContext ? 'invoice-save' : null">
                        {{ $isGettingStartedContext ? $gettingStartedMarker . ' Save' : 'Save' }}
                    </x-primary-button>
                </div>
            </form>
            @endif
        </div>
    </div>

    <script>
        const btn = document.getElementById('useCurrentRate');
        const rateInput = document.getElementById('btc_rate');
        const usdInput  = document.getElementById('amount_usd');
        const btcInput  = document.getElementById('amount_btc');
        const stampEl   = document.getElementById('rateStamp');

        async function fetchRate() {
            try {
                btn.disabled = true;
                btn.textContent = 'Updating…';
                const res = await fetch('{{ route('invoices.rate') }}', { headers: { 'Accept': 'application/json' }});
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.message || 'Rate unavailable');

                // Fill rate
                rateInput.value = Number(data.rate_usd).toFixed(2);

                // If USD present, auto-calc BTC when BTC empty (or stale)
                const usd = parseFloat(usdInput.value);
                if (!isNaN(usd) && usd > 0) {
                    const rate = parseFloat(rateInput.value);
                    if (rate > 0) {
                        btcInput.value = (usd / rate).toFixed(8);
                    }
                }

                // Stamp
                if (data.as_of) stampEl.textContent = `as of ${new Date(data.as_of).toLocaleString()}`;
            } catch (e) {
                alert(e.message || 'Could not fetch rate.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Use current rate';
            }
        }

        btn?.addEventListener('click', fetchRate);


        (() => {
            const usd = document.getElementById('amount_usd');
            const rate = document.getElementById('btc_rate');
            const btc = document.getElementById('amount_btc');

            if (!usd || !rate || !btc) return;

            let active = null; // 'usd' | 'btc' | 'rate'

            const parse = (el) => {
                const v = parseFloat(el.value);
                return Number.isFinite(v) ? v : null;
            };

            const recalc = () => {
                const r = parse(rate);
                if (!r || r <= 0) return;

                if (active === 'usd') {
                    const u = parse(usd);
                    if (u != null) btc.value = (u / r).toFixed(8);
                } else if (active === 'btc') {
                    const b = parse(btc);
                    if (b != null) usd.value = (b * r).toFixed(2);
                } else if (active === 'rate') {
                    // Rate changed: prefer to recompute BTC if USD present, else USD if BTC present
                    const u = parse(usd), b = parse(btc);
                    if (u != null) btc.value = (u / r).toFixed(8);
                    else if (b != null) usd.value = (b * r).toFixed(2);
                }
            };

            const on = (el, name) => el.addEventListener('input', () => { active = name; recalc(); });
            on(usd,  'usd');
            on(btc,  'btc');
            on(rate, 'rate');
        })();


    </script>

</x-app-layout>
