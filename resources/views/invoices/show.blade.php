@if(!request()->routeIs('invoices.public-print'))
    <x-emoji-favicon symbol="💸" bg="#FEF3C7" />
@endif
<x-app-layout>
    <x-slot name="header">
        @php
            $st = $invoice->status ?? 'draft';
            $summary = $paymentSummary ?? [
                'expected_usd' => null,
                'expected_btc_formatted' => null,
                'expected_sats' => null,
                'received_usd' => 0.0,
                'received_sats' => null,
                'confirmed_usd' => 0.0,
                'confirmed_sats' => null,
                'outstanding_usd' => null,
                'outstanding_btc_formatted' => null,
                'outstanding_btc_float' => null,
                'outstanding_sats' => null,
                'last_payment_detected_at' => null,
                'last_payment_confirmed_at' => null,
            ];
        @endphp
        @php $billingDetails = $billingDetails ?? $invoice->billingDetails(); @endphp
        @if (!empty($billingDetails['heading']))
            <p class="mb-1 text-xs font-semibold uppercase tracking-[0.2em] text-gray-500">
                {{ $billingDetails['heading'] }}
            </p>
        @endif
        @php
            $statusBadgeStyle = null;
            if (in_array($st, ['sent', 'pending'], true)) {
                $statusBadgeStyle = 'background-color:#e0e7ff !important; color:#0f172a !important;';
            } elseif ($st === 'partial') {
                $statusBadgeStyle = 'background-color:#cffafe !important; color:#164e63 !important;';
            } elseif ($st === 'void') {
                $statusBadgeStyle = 'background-color:#fef3c7 !important; color:#713f12 !important;';
            }
        @endphp
        <h2 class="text-xl font-semibold leading-tight">
            Invoice <span class="text-gray-500">#{{ $invoice->number }}</span>
            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
      @switch($st)
        @case('paid') bg-green-100 text-green-800 @break
        @case('sent') bg-blue-100 text-slate-900 @break
        @case('partial') bg-cyan-100 text-cyan-900 @break
        @case('pending') bg-blue-100 text-slate-900 @break
        @case('void') bg-yellow-100 text-yellow-900 @break
        @default bg-gray-100 text-gray-800
      @endswitch"
                  @if ($statusBadgeStyle) style="{{ $statusBadgeStyle }}" @endif>
      {{ strtoupper($st) }}
            </span>
        </h2>
    </x-slot>


    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-md border bg-red-50 p-4 text-sm text-red-700" style="border-color: currentColor;">
                    {{ session('error') }}
                </div>
            @endif

            @php
                $hasDraftOnChainPayments = ($invoice->status ?? 'draft') === 'draft'
                    && $invoice->payments->contains(fn ($payment) => !$payment->is_adjustment);
            @endphp

            @if ($hasDraftOnChainPayments)
                <div class="rounded-lg border bg-red-50 p-4 text-sm text-red-900" style="border-color: currentColor;" data-draft-onchain-warning="true">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold">On-chain payments detected while this invoice is still Draft.</p>
                            <p class="mt-1">Mark it sent so the status matches payment activity.</p>
                        </div>
                        <form method="POST" action="{{ route('invoices.set-status', ['invoice' => $invoice, 'action' => 'sent']) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            <a href="#"
                               data-draft-onchain-mark-sent-link="true"
                               class="text-sm font-semibold text-red-700 underline hover:text-red-800"
                               onclick="event.preventDefault(); this.closest('form').submit();">
                                Mark sent
                            </a>
                        </form>
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3">
                <a href="{{ route('invoices.index') }}" class="text-sm text-gray-600 hover:underline">← Back to Invoices</a>
                @php
                    $st = $invoice->status ?? 'draft';
                    $canMarkSent = !in_array($st, ['sent','paid','void']);
                    $canVoid     = !in_array($st, ['void', 'paid'], true);
                    $canResetToDraft = $st !== 'paid';
                @endphp

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Mark sent --}}
                    <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'sent']) }}" class="inline">
                        @csrf @method('PATCH')
                        <x-secondary-button type="submit" :disabled="!$canMarkSent">
                            Mark sent
                        </x-secondary-button>
                    </form>

                    {{-- Void --}}
                    <form method="POST"
                          action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'void']) }}"
                          class="inline"
                          onsubmit="return confirm('Void invoice {{ $invoice->number }}? ');">
                        @csrf @method('PATCH')
                        <x-danger-button
                            type="submit"
                            :disabled="!$canVoid"
                            data-void-button="true"
                            data-void-disabled="{{ $canVoid ? 'false' : 'true' }}">
                            Void
                        </x-danger-button>
                    </form>

                    {{-- Reset to draft (undo) --}}
                    @if ($st !== 'draft')
                        <form method="POST" action="{{ route('invoices.set-status', ['invoice'=>$invoice,'action'=>'draft']) }}" class="inline">
                            @csrf @method('PATCH')
                            <x-secondary-button
                                type="submit"
                                :disabled="!$canResetToDraft"
                                data-reset-draft-button="true"
                                data-reset-draft-disabled="{{ $canResetToDraft ? 'false' : 'true' }}">
                                Reset to draft
                            </x-secondary-button>
                        </form>
                    @endif

                    <a href="{{ route('invoices.edit', $invoice) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>

                    <form method="POST"
                          action="{{ route('invoices.destroy', $invoice) }}"
                          class="inline"
                          onsubmit="return confirm('Delete invoice {{ $invoice->number }}? This moves it to trash.');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Delete</x-danger-button>
                    </form>

                    <a href="{{ route('invoices.print', $invoice) }}"
                       target="_blank" rel="noopener"
                       class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                        Print
                    </a>

                </div>

            </div>

            @if (!empty($billingDetails['footer_note']))
                <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700">
                    <h3 class="text-sm font-semibold text-gray-700 mb-1">Footer note</h3>
                    <p class="whitespace-pre-line">{{ $billingDetails['footer_note'] }}</p>
                </div>
            @endif

            <div class="rounded-lg border border-yellow-100 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 space-y-2" style="border-color: currentColor;">
                <p>Overpayments are treated as gratuities by default. If a payment went over in error, coordinate with your client to refund or apply the surplus as a credit.</p>
                <p class="text-xs text-yellow-800">Need to reconcile an over/under payment? Enter a manual adjustment near the bottom of the screen so the ledger stays accurate without touching the original chain data.</p>
            </div>

            @php
                $canDeliver = $invoice->client && !empty($invoice->client->email) && $invoice->public_enabled;
            @endphp

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Send invoice email</h3>
                        <p class="text-xs text-gray-500">Emails include the public share link, summary, and optional note.</p>
                    </div>
                    @if (!$invoice->public_enabled)
                        <span class="text-xs text-red-600">Enable public link first</span>
                    @elseif (!$invoice->client || empty($invoice->client->email))
                        <span class="text-xs text-red-600">Add a client email first</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('invoices.deliver', $invoice) }}" class="mt-3 space-y-3">
                    @csrf
                    <textarea name="message" rows="2" class="w-full rounded border-gray-300 text-sm"
                              placeholder="Optional note to include in the email">{{ old('message') }}</textarea>
                    @error('message')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="cc_self" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                               @checked(old('cc_self'))>
                        CC myself
                    </label>
                    <div>
                        <x-primary-button type="submit" :disabled="!$canDeliver">Send invoice</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="grid grid-cols-1 gap-0 md:grid-cols-2">
                    <div class="p-6 border-b md:border-b-0 md:border-r">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Summary</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">Client</dt><dd>{{ $invoice->client->name ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Status</dt><dd class="uppercase">{{ $invoice->status ?? 'draft' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Invoice date</dt><dd>{{ optional($invoice->invoice_date)->toDateString() ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Due date</dt><dd>{{ optional($invoice->due_date)->toDateString() ?: '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">Paid at</dt><dd>{{ optional($invoice->paid_at)->toDateTimeString() ?: '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="p-6 border-b">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Description</h3>
                        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $invoice->description ?: '—' }}</p>
                    </div>

                    <div class="p-6 border-b md:border-b-0">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Biller</h3>
                        <dl class="space-y-1 text-sm text-gray-700">
                            <div class="flex justify-between"><dt>Name</dt><dd>{{ $billingDetails['name'] ?? $invoice->user->name }}</dd></div>
                            @if (!empty($billingDetails['email']))
                                <div class="flex justify-between"><dt>Email</dt><dd><a href="mailto:{{ $billingDetails['email'] }}" class="text-indigo-600 hover:underline">{{ $billingDetails['email'] }}</a></dd></div>
                            @endif
                            @if (!empty($billingDetails['phone']))
                                <div class="flex justify-between"><dt>Phone</dt><dd>{{ $billingDetails['phone'] }}</dd></div>
                            @endif
                        </dl>
                        @if (!empty($billingDetails['address_lines']))
                            <div class="mt-3 text-sm text-gray-700">
                                @foreach ($billingDetails['address_lines'] as $line)
                                    <div>{{ $line }}</div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="p-6">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">Amounts</h3>
                        @php
                            $rateInfo = $rate ?? null;
                        @endphp

                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-600">USD</dt><dd>${{ number_format($invoice->amount_usd, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC rate (USD/BTC)</dt><dd>{{ $displayRateUsd !== null ? $displayRateUsd : '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-600">BTC</dt><dd>{{ $displayAmountBtc !== null ? $displayAmountBtc : '—' }}</dd></div>
                        </dl>

                        @php
                            $currency = fn (?float $value) => $value === null ? '—' : ('$' . number_format($value, 2));
                        @endphp

                        @if (!is_null($summary['expected_usd']))
                            <div class="mt-4 rounded-lg border border-indigo-100 bg-indigo-50/70 p-4 text-sm text-indigo-900 space-y-2" style="border-color: currentColor;">
                                <div class="flex justify-between">
                                    <span>Expected</span>
                                    <span>
                                        {{ $currency($summary['expected_usd']) }}
                                        @if (!empty($summary['expected_btc_formatted']))
                                            ({{ $summary['expected_btc_formatted'] }} BTC)
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Received (detected)</span>
                                    <span>{{ $currency($summary['received_usd']) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Confirmed (counts toward status)</span>
                                    <span class="text-right">
                                        {{ $currency($summary['confirmed_usd']) }}
                                        @php
                                            $confirmedBtc = !empty($summary['confirmed_sats'])
                                                ? $invoice->formatBitcoinAmount($summary['confirmed_sats'] / \App\Models\Invoice::SATS_PER_BTC)
                                                : null;
                                        @endphp
                                        @if ($confirmedBtc)
                                            <div class="text-[11px] text-indigo-800">≈ {{ $confirmedBtc }} BTC</div>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between font-semibold">
                                    <span>Outstanding balance (confirmed)</span>
                                    <span>
                                        {{ $currency($summary['outstanding_usd']) }}
                                @if (!empty($summary['outstanding_btc_formatted']))
                                    ({{ $summary['outstanding_btc_formatted'] }} BTC)
                                @endif
                            </span>
                        </div>
                        @if (!empty($summary['outstanding_btc_formatted']))
                            <p class="text-xs text-indigo-800">
                                BTC target is updated using the latest rate and QR code also reflects the updated remaining balance.
                            </p>
                        @endif

                        @php
                            $smallBalanceThreshold = $summary['small_balance_threshold_usd'] ?? null;
                            $canResolveSmall = ($summary['outstanding_usd'] ?? 0) > 0
                                && $smallBalanceThreshold !== null
                                && ($summary['outstanding_usd'] ?? 0) <= $smallBalanceThreshold
                                && $invoice->status !== 'paid';
                        @endphp
                        @if ($canResolveSmall)
                            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900" style="border-color: currentColor;">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-semibold">Resolve small balance</div>
                                        <div class="text-xs text-emerald-800">Creates a manual credit for the remaining amount and marks the invoice paid (threshold {{ $currency($smallBalanceThreshold) }}).</div>
                                    </div>
                                    <form method="POST" action="{{ route('invoices.payments.adjustments.resolve', $invoice) }}">
                                        @csrf
                                        <x-primary-button class="text-sm">Resolve {{ $currency($summary['outstanding_usd']) }}</x-primary-button>
                                    </form>
                                </div>
                            </div>
                        @endif
                            </div>
                        @endif

                        @php
                            $lastDetected = $summary['last_payment_detected_at'] ?? null;
                            $lastConfirmed = $summary['last_payment_confirmed_at'] ?? null;
                        @endphp

                        @if ($lastDetected)
                            <p class="mt-2 text-xs text-gray-500">
                                Last payment detected
                                {{ $lastDetected->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }}
                                @if ($lastConfirmed)
                                    (confirmed {{ $lastConfirmed->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }})
                                @endif
                            </p>
                        @endif

                        @if ($invoice->hasSignificantOverpayment())
                            <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900" style="border-color: currentColor;">
                                Tip detected — this invoice has received more BTC than requested. Consider crediting or refunding the surplus.
                            </div>
                        @endif

                        @if ($invoice->hasSignificantUnderpayment())
                            <div class="mt-3 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900" style="border-color: currentColor;">
                                Underpayment detected — the outstanding balance exceeds the tolerance. Follow up with the client or record a manual adjustment.
                            </div>
                        @endif

                        @if ($invoice->requiresClientOverpayAlert())
                            <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900" style="border-color: currentColor;">
                                Client alert will show on the public invoice (overpayment ~{{ number_format($invoice->overpaymentPercent(), 1) }}%). Overpayments are gratuities unless you manually adjust.
                            </div>
                        @elseif ($invoice->requiresClientUnderpayAlert())
                            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900" style="border-color: currentColor;">
                                Client alert will show on the public invoice (underpayment ~{{ number_format($invoice->underpaymentPercent(), 1) }}%). Follow up with the client or adjust the balance.
                            </div>
                        @endif

                        @php
                            $asOf = null;
                            $asOfUtcIso = null;
                            $asOfFallback = null;

                            if ($rateInfo && !empty($rateInfo['as_of'])) {
                                $asOf = $rateInfo['as_of'] instanceof \Carbon\Carbon
                                    ? $rateInfo['as_of']
                                    : \Carbon\Carbon::parse($rateInfo['as_of']);

                                if ($asOf) {
                                    $asOfUtcIso = $asOf->copy()->utc()->toIso8601String();
                                    $asOfFallback = $asOf->copy()
                                        ->setTimezone(config('app.timezone'))
                                        ->toDayDateTimeString();
                                }
                            }
                        @endphp

                        @if($rateInfo && $asOf && $asOfUtcIso)
                            <div class="mt-2 flex items-center justify-between">
                                <p class="text-xs text-gray-500">
                                    Rate as of
                                    <time
                                        datetime="{{ $asOfUtcIso }}"
                                        data-utc-ts="{{ $asOfUtcIso }}"
                                        title="{{ $asOfFallback }}"
                                        class="font-medium text-gray-600"
                                    >{{ $asOfFallback }}</time>
                                    <span class="ml-2 text-gray-400">({{ $rateInfo['source'] ?? 'spot' }})</span>
                                </p>
                                <form method="POST" action="{{ route('invoices.rate.refresh') }}" class="inline">
                                    @csrf
                                    <x-secondary-button type="submit">Refresh rate</x-secondary-button>
                                </form>
                            </div>
                        @endif


                    </div>
                </div>

                <div class="p-6 border-t"> <!-- ---------------------------------------     Payment Details    ----------------------------------------------------- -->
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">BTC address</dt>
                            <dd class="font-mono flex items-center gap-2">
                                <span>{{ $invoice->payment_address ?: '-' }}</span>
                                @if ($invoice->payment_address)
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->payment_address }}">Copy</x-secondary-button>
                                @endif
                            </dd>
                        </div>



                        @php $st = $invoice->status ?? 'draft'; @endphp
                        <div class="flex justify-between">
                            <dt class="text-gray-600">TXID</dt>
                            <dd class="font-mono flex items-center gap-2">
                                @if ($invoice->txid)
                                    <span>{{ \Illuminate\Support\Str::limit($invoice->txid, 18, '…') }}</span>
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->txid }}">Copy</x-secondary-button>
                                @else
                                    <span>-</span>
                                @endif
                            </dd>
                        </div>

                        @if (empty($invoice->txid))
                            <p class="mt-1 text-xs text-gray-500">
                                @if (in_array($st, ['draft','sent']))
                                    A TXID appears after the on-chain payment is received.
                                @elseif ($st === 'paid')
                                    Marked paid without recording a TXID. You can add one later for reference.
                                @endif
                            </p>
                        @endif

                        <div class="flex justify-between">
                            <dt class="text-gray-600">Paid amount (BTC)</dt>
                            <dd class="font-mono">
                                {{ $invoice->payment_amount_formatted ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-gray-600">Confirmations</dt>
                            <dd class="font-mono">
                                {{ $invoice->payment_confirmations ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-gray-600">Confirmation height</dt>
                            <dd class="font-mono">
                                {{ $invoice->payment_confirmed_height ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-gray-600">Detected at</dt>
                            <dd class="font-mono">
                                {{ optional($invoice->payment_detected_at)->toDayDateTimeString() ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between">
                            <dt class="text-gray-600">Confirmed at</dt>
                            <dd class="font-mono">
                                {{ optional($invoice->payment_confirmed_at)->toDayDateTimeString() ?? '—' }}
                            </dd>
                        </div>

                        @php $uri = $displayBitcoinUri; @endphp

                        @if ($uri)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Bitcoin URI</dt>
                                <dd class="font-mono flex items-center gap-2">
                                    <a href="{{ $uri }}" class="text-indigo-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($uri, 48) }}
                                    </a>
                                    <x-secondary-button type="button" data-copy-text="{{ $uri }}">Copy</x-secondary-button>
                                </dd>
                            </div>

                            <div class="mt-6 flex flex-col md:flex-row gap-8 md:items-center">
                                <!-- Left: QR (fixed width) -->
                                <div class="md:w-[260px] md:flex-none">
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment QR</h3>
                                    {{--<canvas id="qrBitcoin" width="220" height="220"
                                            class="rounded-lg border border-gray-200 bg-white p-2"></canvas>--}}
                                    {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(220)->margin(1)->generate($displayBitcoinUri) !!}

                                    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                                    <script>
                                        document.addEventListener('DOMContentLoaded', () => {
                                            const uri = @json($displayBitcoinUri);
                                            const img = document.getElementById('qrBitcoin'); // <-- your <img id="qrBitcoin">
                                            if (!uri || !img) return;

                                            QRCode.toDataURL(uri, { width: 220, margin: 1, errorCorrectionLevel: 'M' }, (err, url) => {
                                                if (err) return console.error('QR error:', err);
                                                img.src = url;
                                            });
                                        });
                                    </script>

                                    <p class="mt-2 text-xs text-gray-500">Scan with any Bitcoin wallet.</p>
                                    <p class="mt-1 text-[11px] text-gray-500 leading-snug">
                                        BTC/USD is captured when this page loads. To avoid over/underpayment and additional miner fees,
                                        refresh right before sending payment; printed copies may be stale.
                                    </p>
                                    <div class="mt-3 rounded border border-amber-100 bg-amber-50 p-3 text-xs text-amber-900" style="border-color: currentColor;">
                                        <strong>Send one payment:</strong> please send the entire outstanding balance in a single transaction.
                                        Splitting the invoice across multiple payments usually adds miner fees and can delay settlement.
                                    </div>
                                </div>

                                <!-- Right: big centered Thank you -->
                                <div class="flex-1 min-h-[220px] flex items-center justify-center">
                                    <div class="select-none text-6xl md:text-7xl font-extrabold leading-none tracking-tight">
                                        <span class="text-indigo-950" style="text-shadow: 1px 1px 2px rgba(255,255,255,0.5), -1px -1px 2px rgba(255,255,255,0.5);">Thank&nbsp;you!</span>
                                    </div>
                                </div>
                            </div>





                            {{-- lightweight client-side QR (no data sent off-box) --}}
                            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const canvas = document.getElementById('qrBitcoin');
                                    const uri = @json($uri);
                                    if (canvas && uri) {
                                        QRCode.toCanvas(canvas, uri, { width: 180, margin: 2 }, err => {
                                            if (err) console.error('QR render failed', err);
                                        });
                                    }
                                });
                            </script>
                        @endif

                    </dl>
                </div>

            </div>

            <div class="space-y-6">
                @if ($invoice->deliveries->isNotEmpty())
                    @php $deliveryCount = $invoice->deliveries->count(); @endphp
                    <style>
                        details.delivery-log[open] .show-label { display: none; }
                        details.delivery-log:not([open]) .hide-label { display: none; }
                        summary.delivery-log-summary { cursor: pointer; }
                    </style>
                    <div class="rounded-lg bg-white shadow">
                        <details class="delivery-log" open>
                            <summary class="delivery-log-summary flex select-none items-center justify-between px-6 py-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-700">Delivery log</h3>
                                    <p class="text-xs text-gray-500">{{ $deliveryCount }} entr{{ $deliveryCount === 1 ? 'y' : 'ies' }}</p>
                                </div>
                                <span class="text-xs text-indigo-600 show-label">Show</span>
                                <span class="text-xs text-gray-500 hide-label">Hide</span>
                            </summary>
                            <div class="border-t border-gray-100 px-6 pb-6">
                                <div class="overflow-y-auto overflow-x-auto rounded border border-gray-100" style="max-height: 24rem;">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                            <tr>
                                                <th class="px-2 py-2 text-left">Type</th>
                                                <th class="px-2 py-2 text-left">Recipient</th>
                                                <th class="px-2 py-2 text-left">Status</th>
                                                <th class="px-2 py-2 text-left">Queued</th>
                                                <th class="px-2 py-2 text-left">Sent</th>
                                                <th class="px-2 py-2 text-left">Error</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach ($invoice->deliveries as $delivery)
                                                <tr>
                                                    <td class="px-2 py-2 uppercase text-xs">{{ $delivery->type }}</td>
                                                    <td class="px-2 py-2">
                                                        {{ $delivery->recipient }}
                                                        @if ($delivery->cc)
                                                            <div class="text-xs text-gray-500">cc: {{ $delivery->cc }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2">{{ ucfirst($delivery->status) }}</td>
                                                    @php
                                                        $queued = $delivery->dispatched_at;
                                                        $queuedIso = $queued ? $queued->copy()->utc()->toIso8601String() : null;
                                                        $queuedDisplay = $queued ? $queued->copy()->setTimezone(config('app.timezone'))->toDayDateTimeString() : null;
                                                        $sent = $delivery->sent_at;
                                                        $sentIso = $sent ? $sent->copy()->utc()->toIso8601String() : null;
                                                        $sentDisplay = $sent ? $sent->copy()->setTimezone(config('app.timezone'))->toDayDateTimeString() : null;
                                                    @endphp
                                                    <td class="px-2 py-2 text-sm text-gray-600">
                                                        @if ($queuedIso)
                                                            <time datetime="{{ $queuedIso }}" data-utc-ts="{{ $queuedIso }}" title="{{ $queuedDisplay }}">{{ $queuedDisplay }}</time>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-gray-600">
                                                        @if ($sentIso)
                                                            <time datetime="{{ $sentIso }}" data-utc-ts="{{ $sentIso }}" title="{{ $sentDisplay }}">{{ $sentDisplay }}</time>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-red-600">
                                                        {{ $delivery->error_message ?: 'None' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    </div>
                @else
                    <div class="rounded-lg bg-white p-6 shadow">
                        <h3 class="text-sm font-semibold text-gray-700">Delivery log</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            No delivery attempts yet. Enable the public link and send the invoice to create the first log entry.
                        </p>
                    </div>
                @endif

                @if ($invoice->payments->isNotEmpty())
                    <div class="rounded-lg bg-white shadow">
                        <div class="p-6">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">Payment history</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-2 py-2 text-left">Detected</th>
                                            <th class="px-2 py-2 text-left">TXID</th>
                                            <th class="px-2 py-2 text-right">BTC</th>
                                            <th class="px-2 py-2 text-right">USD</th>
                                            <th class="px-2 py-2 text-left">Status</th>
                                            <th class="px-2 py-2 text-left">Note</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($invoice->payments as $payment)
                                            <tr>
                                                <td class="px-2 py-2">{{ optional($payment->detected_at)->toDayDateTimeString() ?? '—' }}</td>
                                                <td class="px-2 py-2 font-mono">{{ \Illuminate\Support\Str::limit($payment->txid, 18, '…') }}</td>
                                                <td class="px-2 py-2 text-right">
                                                    {{ $invoice->formatBitcoinAmount($payment->sats_received / \App\Models\Invoice::SATS_PER_BTC) ?? '—' }}
                                                </td>
                                                <td class="px-2 py-2 text-right">
                                                    @if ($payment->fiat_amount !== null)
                                                        <div>${{ number_format($payment->fiat_amount, 2) }}</div>
                                                        @if ($payment->usd_rate !== null)
                                                            <div class="text-xs text-gray-500">
                                                                @ ${{ number_format((float) $payment->usd_rate, 2) }} USD/BTC
                                                            </div>
                                                        @endif
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2">
                                                    @if ($payment->is_adjustment)
                                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                                            {{ $payment->sats_received >= 0 ? 'Manual credit' : 'Manual debit' }}
                                                        </span>
                                                    @else
                                                        {{ $payment->confirmed_at ? 'Confirmed' : 'Pending' }}
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    <div class="text-sm text-gray-800">{{ $payment->note ?: '—' }}</div>
                                                    <form method="POST"
                                                          action="{{ route('invoices.payments.note', [$invoice, $payment]) }}"
                                                          class="mt-2 space-y-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="source_payment_id" value="{{ $payment->id }}">
                                                        <textarea name="note" rows="2"
                                                                  class="w-full rounded border-gray-300 text-sm"
                                                                  placeholder="Add note...">{{ old('source_payment_id') == $payment->id ? old('note') : $payment->note }}</textarea>
                                                        @if ($errors->has('note') && old('source_payment_id') == $payment->id)
                                                            <p class="text-xs text-red-600">{{ $errors->first('note') }}</p>
                                                        @endif
                                                        <div class="flex justify-end">
                                                            <x-secondary-button type="submit" class="text-xs px-3 py-1">
                                                                Save
                                                            </x-secondary-button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-white p-6 shadow text-sm text-gray-500">
                        No payments detected yet. Share the public link with your client to start tracking on-chain activity.
                    </div>
                @endif

                {{-- Public link (shareable print view) --}}
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Public link</h3>
                    @if ($invoice->public_enabled && $invoice->public_url)
                        <div class="flex items-center gap-2">
                            <form action="{{ route('invoices.share.rotate', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Regenerate public link? Old URL will stop working.');">
                                @csrf @method('PATCH')
                                <x-secondary-button type="submit">Rotate link</x-secondary-button>
                            </form>

                            <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Disable public link?');">
                                @csrf @method('PATCH')
                                <x-danger-button>Disable</x-danger-button>
                            </form>
                        </div>
                    @endif
                </div>

                @if (session('public_url'))
                    <div class="mt-2 rounded bg-green-50 p-2 text-sm text-green-700">
                        Link enabled:
                        <a href="{{ session('public_url') }}" target="_blank" rel="noopener" class="underline break-all">
                            {{ session('public_url') }}
                        </a>
                    </div>
                @endif

                @if ($invoice->public_enabled && $invoice->public_url)
                    <div class="mt-3">
                        <div class="flex items-center gap-2">
                            <input type="text" readonly class="w-full rounded-md border-gray-300" value="{{ $invoice->public_url }}">
                            <x-secondary-button type="button" data-copy-text="{{ $invoice->public_url }}">Copy</x-secondary-button>
                            <a href="{{ $invoice->public_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Open
                            </a>
                        </div>
                        @if ($invoice->public_expires_at)
                            @if ($invoice->public_expires_at->isPast())
                                <p class="mt-2 text-xs font-semibold text-red-700" data-public-link-expired="true">
                                    Expired {{ $invoice->public_expires_at->toDayDateTimeString() }}
                                </p>
                                <p class="mt-1 text-xs text-red-700" data-public-link-reactivation-help="true">
                                    To unexpire the public link, first disable it, set the expiry options, and enable the public link again.
                                </p>
                            @else
                                <p class="mt-2 text-xs text-gray-500">Expires {{ $invoice->public_expires_at->toDayDateTimeString() }}</p>
                            @endif
                        @endif
                        <p class="mt-2 text-xs text-amber-700">
                            Tip: remind the client to send the full balance in a single Bitcoin transaction when you share this link.
                            Splitting the payment often increases miner fees.
                        </p>
                    </div>
                @else
                    <form action="{{ route('invoices.share.enable', $invoice) }}" method="POST" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                        @csrf @method('PATCH')

                        <div>
                            <label class="block text-xs font-medium text-gray-600">Expiry preset</label>
                            <select name="expires_preset" class="mt-1 w-full rounded-md border-gray-300">
                                <option value="none" selected>No expiry</option>
                                <option value="24h">24 hours</option>
                                <option value="7d">7 days</option>
                                <option value="30d">30 days</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600">Or pick exact datetime</label>
                            <input type="datetime-local" name="expires" class="mt-1 w-full rounded-md border-gray-300">
                            <p class="mt-1 text-[11px] text-gray-500">If set, this overrides the preset.</p>
                        </div>

                        <div>
                            <x-primary-button class="w-full sm:w-auto">Enable public link</x-primary-button>
                        </div>
                    </form>

                    <p class="mt-2 text-xs text-gray-500">
                        Creates a secret link to a read-only print view that refreshes the BTC rate on each visit.
                    </p>
                @endif

                @if($invoice->public_enabled)
                    <br />
                    <div class="mb-4 rounded-md border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800" style="border-color: currentColor;">
                        <div class="flex flex-wrap items-center gap-1">
                            <span>This invoice is currently public. To edit, first</span>
                            <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Disable the public link?');">
                                @csrf @method('PATCH')
                                <button type="submit" class="underline text-red-600 hover:text-red-700">disable the public link</button>
                            </form>
                            <span>.</span>
                        </div>
                    </div>
                @endif
            </div>
            </div>

            <div class="mt-6 rounded-lg bg-white p-6 shadow">
                <h3 class="text-sm font-semibold text-gray-700">Manual adjustments</h3>
                <p class="mt-1 text-xs text-gray-500">
                    Credit or reopen balances without editing on-chain payments. Use this when a payment misses the tolerance threshold.
                </p>
                <form method="POST" action="{{ route('invoices.payments.adjustments.store', $invoice) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount (USD)</label>
                            <input type="number" name="amount_usd" step="0.01" min="0.01"
                                   value="{{ old('amount_usd') }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('amount_usd')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Direction</label>
                            <select name="direction"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="increase" @selected(old('direction') === 'increase')>Credit invoice (reduce outstanding)</option>
                                <option value="decrease" @selected(old('direction') === 'decrease')>Reopen balance (increase outstanding)</option>
                            </select>
                            @error('direction')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Note (optional)</label>
                        <textarea name="note" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Document the reason for this adjustment">{{ old('note') }}</textarea>
                        @error('note')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>Add adjustment</x-primary-button>
                    </div>
                </form>
            </div>

        </div>
    </div>
    <br />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-utc-ts]').forEach((node) => {
                const iso = node.getAttribute('data-utc-ts');
                if (!iso) return;

                const parsed = new Date(iso);
                if (Number.isNaN(parsed.getTime())) return;

                const localized = parsed.toLocaleString(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                });

                if (localized) {
                    node.textContent = localized;
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            async function copyText(text) {
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(text);
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.top = '-1000px';
                        document.body.appendChild(ta);
                        ta.focus();
                        ta.select();
                        document.execCommand('copy');
                        ta.remove();
                    }
                    return true;
                } catch { return false; }
            }

            document.querySelectorAll('[data-copy-text]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const text = btn.getAttribute('data-copy-text') || '';
                    const ok = await copyText(text);
                    const old = btn.textContent;
                    btn.textContent = ok ? 'Copied' : 'Copy failed';
                    setTimeout(() => btn.textContent = old, 1200);
                });
            });
        });
    </script>


</x-app-layout>
