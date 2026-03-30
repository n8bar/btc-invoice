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
        @php $paymentHistory = $paymentHistory ?? $invoice->paymentHistory(); @endphp
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
            @php
                $gettingStartedContext = request()->boolean('getting_started');
                $showOverpaymentGratuityNote = (bool) ($invoice->user?->show_overpayment_gratuity_note ?? true);
                $showQrRefreshReminder = (bool) ($invoice->user?->show_qr_refresh_reminder ?? true);
                $showSinglePaymentGuidance = $st !== 'paid';
            @endphp

            @isset($gettingStartedStrip)
                @include('getting-started.partials.progress-strip', ['strip' => $gettingStartedStrip])
            @endisset

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

            @if ($invoice->unsupported_configuration_flagged)
                @php
                    $unsupportedInvoiceMessage = $invoice->unsupported_configuration_reason === 'payment_collision'
                        ? 'This invoice was implicated in shared payment-address activity, so automatic payment attribution may be unreliable for this invoice.'
                        : 'This invoice was created while CryptoZing had already flagged this wallet account as unsupported, so automatic payment attribution may be unreliable for this invoice.';
                @endphp
                <div data-unsupported-invoice-banner
                     class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900 shadow-sm dark:border-red-400/50 dark:bg-red-950/40 dark:text-red-100"
                     style="border-color: currentColor;">
                    <p class="font-semibold">Unsupported invoice</p>
                    <p class="mt-1">{{ $unsupportedInvoiceMessage }}</p>
                    <p class="mt-1">Connect a fresh dedicated account key for future invoices that need reliable automatic tracking.</p>
                </div>
            @endif

            @php
                $hasDraftOnChainPayments = ($invoice->status ?? 'draft') === 'draft'
                    && $invoice->payments->contains(fn ($payment) => !$payment->is_adjustment && !$payment->isIgnored());
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

            <div data-invoice-sticky-nav="true" class="sticky top-16 z-20 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white/95 px-4 py-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/90 dark:border-white/10 dark:bg-slate-900/90">
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
                        🖨️ Print
                    </a>

                </div>

            </div>

            <div class="rounded-lg border border-yellow-100 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 space-y-2" style="border-color: currentColor;">
                @if ($showOverpaymentGratuityNote)
                    <p>Overpayments are treated as gratuities by default. If a payment went over in error, coordinate with your client to refund or apply the surplus as a credit.</p>
                @endif
                <p class="text-xs text-yellow-800">Need to reconcile an over/under payment? Enter a manual adjustment near the bottom of the page so the ledger stays accurate without touching the original chain data.</p>
            </div>

            @php
                $canDeliver = $invoice->client && !empty($invoice->client->email) && $invoice->public_enabled;
                $onboardingGlow = 'ring-2 ring-indigo-300 ring-offset-2 ring-offset-white dark:ring-indigo-400/70 dark:ring-offset-slate-900';
                $gettingStartedMarker = '👉';
            @endphp

            <div class="rounded-lg border border-indigo-100 bg-indigo-50/70 p-4 text-sm text-indigo-900 shadow-sm dark:border-indigo-400/30 dark:bg-indigo-950/30 dark:text-indigo-100">
                <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm">
                    <p class="font-semibold">Delivery steps</p>
                    <a href="#public-link-card"
                       class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-1.5 py-1.5 font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/40 dark:bg-slate-900/70 dark:text-indigo-200 dark:hover:bg-indigo-950/50 dark:focus:ring-offset-slate-900">
                        Jump to Public link
                    </a>
                    <a href="#send-invoice-email-card"
                       class="inline-flex items-center rounded-md border border-indigo-300 bg-white px-1.5 py-1.5 font-semibold text-indigo-700 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/40 dark:bg-slate-900/70 dark:text-indigo-200 dark:hover:bg-indigo-950/50 dark:focus:ring-offset-slate-900">
                        Jump to Send invoice email
                    </a>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-indigo-100 bg-indigo-50/60 px-6 py-4 text-sm text-indigo-900 dark:border-indigo-400/30 dark:bg-indigo-950/30 dark:text-indigo-100">
                    Need to update invoice details? <a href="{{ route('invoices.edit', $invoice) }}" class="font-semibold underline hover:text-indigo-700 dark:text-indigo-200 dark:hover:text-indigo-100">edit</a> this invoice.
                </div>
                <div class="grid grid-cols-1 gap-0 md:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)]">
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
                                            <div class="text-[14.67px] text-indigo-800">≈ {{ $confirmedBtc }} BTC</div>
                                        @endif
                                    </span>
                                </div>
                                <div class="flex justify-between font-semibold">
                                    <span>Outstanding balance (confirmed)</span>
                                    <span class="whitespace-nowrap">
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
                                Tip detected. If this overpayment was accidental, consider a refund or credit.
                            </div>
                        @endif

                        @if ($invoice->hasSignificantUnderpayment())
                            <div class="mt-3 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900" style="border-color: currentColor;">
                                Underpayment detected — the outstanding balance exceeds the tolerance. Follow up with the client or record a manual adjustment.
                            </div>
                        @endif

                        @if ($invoice->requiresClientOverpayAlert())
                            <div class="mt-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900" style="border-color: currentColor;">
                                Client alert will show on the public invoice (overpayment ~{{ number_format($invoice->overpaymentPercent(), 1) }}%).
                                @if ($showOverpaymentGratuityNote)
                                    Overpayments are gratuities unless you manually adjust.
                                @endif
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

                @if (!empty($billingDetails['footer_note']))
                    <div class="p-6 border-t">
                        <h3 class="mb-1 text-sm font-semibold text-gray-700">Footer note</h3>
                        <p class="whitespace-pre-line text-sm text-gray-700">{{ $billingDetails['footer_note'] }}</p>
                    </div>
                @endif

                <div class="p-6 border-t"> <!-- ---------------------------------------     Payment Details    ----------------------------------------------------- -->
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Payment Details</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600">BTC address</dt>
                            <dd class="font-mono flex items-center gap-2">
                                <span>{{ $invoice->payment_address ?: '-' }}</span>
                                @if ($invoice->payment_address && $showSinglePaymentGuidance)
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->payment_address }}">📋 Copy</x-secondary-button>
                                @endif
                            </dd>
                        </div>



                        @php $st = $invoice->status ?? 'draft'; @endphp
                        <div class="flex justify-between">
                            <dt class="text-gray-600">TXID</dt>
                            <dd class="font-mono flex items-center gap-2">
                                @if ($invoice->txid)
                                    <span>{{ \Illuminate\Support\Str::limit($invoice->txid, 18, '…') }}</span>
                                    <x-secondary-button type="button" data-copy-text="{{ $invoice->txid }}">📋 Copy</x-secondary-button>
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

                        @if ($uri && $showSinglePaymentGuidance)
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Bitcoin URI</dt>
                                <dd class="font-mono flex items-center gap-2">
                                    <a href="{{ $uri }}" class="text-indigo-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($uri, 48) }}
                                    </a>
                                    <x-secondary-button type="button" data-copy-text="{{ $uri }}">📋 Copy</x-secondary-button>
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
                                    @if ($showQrRefreshReminder)
                                        <p class="mt-1 text-[14.67px] text-gray-500 leading-snug">
                                            BTC/USD is captured when this page loads. To avoid over/underpayment and additional miner fees,
                                            refresh right before sending payment; printed copies may be stale.
                                        </p>
                                    @endif
                                    @if ($showSinglePaymentGuidance)
                                        <div class="mt-3 rounded border border-amber-100 bg-amber-50 p-3 text-xs text-amber-900" style="border-color: currentColor;">
                                            <strong>Send one payment (if possible):</strong> please send the entire outstanding balance in a single transaction.
                                            If you need to split across wallets, multiple payments are accepted, but may add miner fees and delay settlement.
                                        </div>
                                    @endif
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
                                                <th class="px-2 py-2 text-left">Notice</th>
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
                                                    <td class="px-2 py-2 text-sm font-medium text-gray-900">{{ $delivery->typeLabel() }}</td>
                                                    <td class="px-2 py-2">
                                                        {{ $delivery->recipient }}
                                                        @if ($delivery->cc)
                                                            <div class="text-xs text-gray-500">cc: {{ $delivery->cc }}</div>
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2">{{ $delivery->statusLabel() }}</td>
                                                    @php
                                                        $queued = $delivery->dispatched_at;
                                                        $queuedIso = $queued ? $queued->copy()->utc()->toIso8601String() : null;
                                                        $queuedCompactDisplay = $queued ? $queued->copy()->setTimezone(config('app.timezone'))->format('m-d-y H:i') : null;
                                                        $queuedDisplay = $queued ? $queued->copy()->setTimezone(config('app.timezone'))->toDayDateTimeString() : null;
                                                        $sent = $delivery->sent_at;
                                                        $sentIso = $sent ? $sent->copy()->utc()->toIso8601String() : null;
                                                        $sentCompactDisplay = $sent ? $sent->copy()->setTimezone(config('app.timezone'))->format('m-d-y H:i') : null;
                                                        $sentDisplay = $sent ? $sent->copy()->setTimezone(config('app.timezone'))->toDayDateTimeString() : null;
                                                    @endphp
                                                    <td class="px-2 py-2 text-sm text-gray-600">
                                                        @if ($queuedIso)
                                                            <time datetime="{{ $queuedIso }}" data-utc-compact-ts="{{ $queuedIso }}" title="{{ $queuedDisplay }}">{{ $queuedCompactDisplay }}</time>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    <td class="px-2 py-2 text-sm text-gray-600">
                                                        @if ($sentIso)
                                                            <time datetime="{{ $sentIso }}" data-utc-compact-ts="{{ $sentIso }}" title="{{ $sentDisplay }}">{{ $sentCompactDisplay }}</time>
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                    @php
                                                        $hasDeliveryError = filled($delivery->error_message);
                                                        $deliveryErrorMessage = $hasDeliveryError ? $delivery->error_message : 'None';
                                                    @endphp
                                                    <td class="px-2 py-2 text-sm">
                                                        <div class="block max-w-[10rem] truncate {{ $hasDeliveryError ? 'text-red-600' : 'text-gray-500' }}"
                                                             title="{{ $deliveryErrorMessage }}">
                                                            {{ $deliveryErrorMessage }}
                                                        </div>
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

                @if ($paymentHistory->isNotEmpty())
                    <div class="rounded-lg bg-white shadow">
                        <div class="p-6">
                            <h3 class="mb-3 text-sm font-semibold text-gray-700">Payment history</h3>
                            @if ($invoice->canSendReceipt())
                                @php
                                    $latestReceiptDelivery = $invoice->latestDeliveryOfType('receipt');
                                    $receiptReviewReasons = $invoice->receiptReviewReasons();
                                @endphp
                                @php
                                    $receiptPanelNeedsAttention = $receiptReviewReasons !== [];
                                    $receiptPanelClasses = $receiptPanelNeedsAttention
                                        ? 'border-amber-200 bg-amber-50/80 text-amber-950'
                                        : 'border-indigo-200 bg-indigo-50/80 text-indigo-950';
                                    $receiptEyebrowClasses = $receiptPanelNeedsAttention
                                        ? 'text-amber-800'
                                        : 'text-indigo-800';
                                    $receiptMetaClasses = $receiptPanelNeedsAttention
                                        ? 'text-amber-900'
                                        : 'text-indigo-900';
                                @endphp
                                <div id="receipt-review-panel"
                                     data-receipt-review-panel="true"
                                     class="invoice-anchor-target mb-4 rounded-lg border p-4 text-sm {{ $receiptPanelClasses }}">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="space-y-2">
                                            <div class="space-y-1">
                                                <p class="text-xs font-semibold uppercase tracking-[0.15em] {{ $receiptEyebrowClasses }}">
                                                    {{ $receiptPanelNeedsAttention ? 'Review before sending' : 'Receipt ready to review' }}
                                                </p>
                                                <p class="text-base font-semibold">Client receipt</p>
                                                @if ($receiptPanelNeedsAttention)
                                                    <p>Review the payment rows below before sending the client receipt. The receipt stays manual because higher-certainty payment confirmation still depends on owner review.</p>
                                                @else
                                                    <p>The client receipt is ready for review and send. A narrow payment acknowledgment may already have gone out automatically, but the client receipt still goes out only after you review it here.</p>
                                                @endif
                                            </div>
                                            @if ($invoice->needsReceiptReview())
                                                <div class="md:hidden">
                                                    <form method="POST" action="{{ route('invoices.deliver.receipt', $invoice) }}">
                                                        @csrf
                                                        <x-secondary-button type="submit">Send receipt</x-secondary-button>
                                                    </form>
                                                </div>
                                            @endif
                                            @if ($latestReceiptDelivery)
                                                <p class="text-xs {{ $receiptMetaClasses }}">
                                                    Latest receipt attempt: <span class="font-semibold">{{ $latestReceiptDelivery->statusLabel() }}</span>.
                                                </p>
                                            @else
                                                <p class="text-xs {{ $receiptMetaClasses }}">No client receipt has been queued or sent yet.</p>
                                            @endif
                                            @if ($receiptReviewReasons !== [])
                                                <div class="pt-1 text-xs text-amber-900">
                                                    <p class="font-semibold">Review these payment-history conditions before sending the client receipt.</p>
                                                    <ul class="ml-4 mt-1 list-disc space-y-1">
                                                        @foreach ($receiptReviewReasons as $receiptReviewReason)
                                                            <li>{{ $receiptReviewReason }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                        @if ($invoice->needsReceiptReview())
                                            <form method="POST" action="{{ route('invoices.deliver.receipt', $invoice) }}" class="hidden md:block">
                                                @csrf
                                                <x-secondary-button type="submit">Send receipt</x-secondary-button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
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
                                            <th class="px-2 py-2 text-left">Correction</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($paymentHistory as $payment)
                                            @php
                                                $showIgnoreForm = (string) old('correction_payment_id') === (string) $payment->id && $errors->has('ignore_reason');
                                                $showReattributeForm = (string) old('correction_payment_id') === (string) $payment->id
                                                    && ($errors->has('destination_invoice_id') || $errors->has('reattribute_reason'));
                                                $showCorrectionMenu = $showIgnoreForm || $showReattributeForm;
                                                $isSourcePayment = $payment->belongsToSourceInvoice($invoice);
                                                $isOutgoingReattribution = $payment->isReattributedOutFrom($invoice);
                                                $isInboundReattribution = $payment->isReattributedInto($invoice);
                                                $relatedSourceInvoice = $payment->sourceInvoice;
                                                $relatedDestinationInvoice = $payment->accountingInvoice;
                                                $correctionRouteInvoice = $isSourcePayment ? $invoice : $relatedSourceInvoice;
                                                $selectedDestinationId = $showReattributeForm
                                                    ? old('destination_invoice_id')
                                                    : ($isOutgoingReattribution ? $payment->activeAccountingInvoiceId() : null);
                                                $hasManualAdjustmentReversal = $payment->is_adjustment
                                                    && $paymentHistory->contains(fn ($candidate) => $candidate->id !== $payment->id
                                                        && $candidate->is_adjustment
                                                        && $candidate->note === 'reversal of '.$payment->txid);
                                                $canUndoReattribution = $payment->isReattributed() && $correctionRouteInvoice;
                                                $canIgnorePayment = $isSourcePayment
                                                    && ! $payment->is_adjustment
                                                    && ! $payment->isIgnored()
                                                    && ! $payment->isReattributed();
                                                $correctionLabelLines = ['Corrections'];
                                                $correctionButtonClasses = 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:ring-indigo-500 dark:border-white/15 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus:ring-indigo-400';
                                                $correctionPanelClasses = 'border-gray-200 bg-white dark:border-white/10 dark:bg-slate-900';

                                                if ($payment->isIgnored()) {
                                                    $correctionLabelLines = ['Ignored'];
                                                    $correctionButtonClasses = 'border-amber-200 bg-amber-50 text-amber-900 hover:bg-amber-100 focus:ring-amber-500 dark:border-amber-200/80 dark:bg-amber-200 dark:text-amber-950 dark:hover:bg-amber-100 dark:focus:ring-amber-300';
                                                    $correctionPanelClasses = 'border-amber-200 bg-amber-50/70 dark:border-amber-400/35 dark:bg-slate-900';
                                                } elseif ($isOutgoingReattribution) {
                                                    $correctionLabelLines = ['Reapplied', 'Elsewhere'];
                                                    $correctionButtonClasses = 'border-sky-200 bg-sky-50 text-sky-900 hover:bg-sky-100 focus:ring-sky-500 dark:border-sky-300/80 dark:bg-sky-500 dark:text-white dark:hover:bg-sky-400 dark:focus:ring-sky-300';
                                                    $correctionPanelClasses = 'border-sky-200 bg-sky-50/70 dark:border-sky-400/35 dark:bg-slate-900';
                                                } elseif ($isInboundReattribution) {
                                                    $correctionLabelLines = ['Applied', 'Here'];
                                                    $correctionButtonClasses = 'border-emerald-200 bg-emerald-50 text-emerald-900 hover:bg-emerald-100 focus:ring-emerald-500 dark:border-emerald-300/80 dark:bg-emerald-500 dark:text-white dark:hover:bg-emerald-400 dark:focus:ring-emerald-300';
                                                    $correctionPanelClasses = 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-400/35 dark:bg-slate-900';
                                                }

                                                $correctionLabelText = implode(' ', $correctionLabelLines);
                                            @endphp
                                            <tr id="payment-row-{{ $payment->id }}" class="invoice-anchor-target">
                                                @php
                                                    $paymentDetectedAt = $payment->detected_at;
                                                    $paymentDetectedIso = $paymentDetectedAt ? $paymentDetectedAt->copy()->utc()->toIso8601String() : null;
                                                    $paymentDetectedCompactDisplay = $paymentDetectedAt ? $paymentDetectedAt->copy()->setTimezone(config('app.timezone'))->format('m-d-y H:i') : null;
                                                    $paymentDetectedDisplay = $paymentDetectedAt ? $paymentDetectedAt->copy()->setTimezone(config('app.timezone'))->toDayDateTimeString() : null;
                                                @endphp
                                                <td class="px-2 py-2 text-sm text-gray-600">
                                                    @if ($paymentDetectedIso)
                                                        <time datetime="{{ $paymentDetectedIso }}" data-utc-compact-ts="{{ $paymentDetectedIso }}" title="{{ $paymentDetectedDisplay }}">{{ $paymentDetectedCompactDisplay }}</time>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2 font-mono">
                                                    @if ($payment->txid)
                                                        <div class="max-h-[6.5rem] max-w-[9rem] overflow-y-auto break-all text-[15px] leading-[1.05rem]">{{ $payment->txid }}</div>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2 text-right">
                                                    <div class="font-mono text-[15px] leading-[1.05rem]">
                                                        {{ number_format($payment->sats_received / \App\Models\Invoice::SATS_PER_BTC, 8, '.', '') }}
                                                    </div>
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
                                                        <span class="font-medium text-gray-700">{{ $payment->confirmed_at ? 'Confirmed' : 'Pending' }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    @if ($isSourcePayment)
                                                        <form method="POST"
                                                              action="{{ route('invoices.payments.note', [$invoice, $payment]) }}"
                                                              class="flex flex-col gap-1"
                                                              data-payment-note-form
                                                              data-payment-note-container>
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="source_payment_id" value="{{ $payment->id }}">
                                                            <textarea name="note" rows="2"
                                                                      class="min-h-[5.5rem] w-full resize-none overflow-y-auto rounded border-gray-300 text-sm leading-5"
                                                                      placeholder="Add note..."
                                                                      data-payment-note-input
                                                                      data-payment-note-field>{{ old('source_payment_id') == $payment->id ? old('note') : $payment->note }}</textarea>
                                                            <p class="text-xs text-gray-500" data-payment-note-save-state aria-live="polite"></p>
                                                            @if ($errors->has('note') && old('source_payment_id') == $payment->id)
                                                                <p class="text-xs text-red-600">{{ $errors->first('note') }}</p>
                                                            @endif
                                                        </form>
                                                    @else
                                                        <div x-data="{ showReadonlyNoteHint: false }" class="flex flex-col gap-1" data-payment-note-container>
                                                            <textarea rows="2"
                                                                      readonly
                                                                      class="min-h-[5.5rem] w-full resize-none overflow-y-auto rounded border-gray-300 bg-gray-50 text-sm text-gray-700 leading-5"
                                                                      placeholder="No note."
                                                                      @focus="showReadonlyNoteHint = true"
                                                                      @click="showReadonlyNoteHint = true"
                                                                      data-payment-note-field>{{ $payment->note }}</textarea>
                                                            @if ($relatedSourceInvoice)
                                                                <p x-cloak
                                                                   x-show="showReadonlyNoteHint"
                                                                   class="text-xs text-gray-500">
                                                                    Edit notes on
                                                                    <a href="{{ route('invoices.show', $relatedSourceInvoice) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">
                                                                        {{ $relatedSourceInvoice->number }}
                                                                    </a>.
                                                                </p>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-2 py-2 align-top">
                                                    @if ($payment->is_adjustment)
                                                        <div class="w-28" x-data="{ open: false }">
                                                            @if ($hasManualAdjustmentReversal)
                                                                <span class="inline-flex flex-col text-xs text-gray-500">
                                                                    <span>Reversed</span>
                                                                    <span>entry</span>
                                                                </span>
                                                            @else
                                                                <button type="button"
                                                                        class="inline-flex w-full items-center justify-center rounded-md border border-rose-200 bg-white px-3 py-2 text-center text-xs font-semibold leading-tight text-rose-700 shadow-sm transition hover:border-rose-300 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 dark:border-rose-400/35 dark:bg-slate-900/80 dark:text-rose-200 dark:hover:border-rose-300/60 dark:hover:bg-rose-950/35 dark:focus:ring-rose-400 dark:focus:ring-offset-slate-900"
                                                                        @click="open = !open"
                                                                        :aria-expanded="open ? 'true' : 'false'">
                                                                    <span class="flex flex-col items-center">
                                                                        <span>Reverse</span>
                                                                        <span>adjustment</span>
                                                                    </span>
                                                                </button>
                                                                <div x-cloak
                                                                     x-show="open"
                                                                     x-transition
                                                                     class="mt-2 space-y-2 rounded-lg border border-rose-200 bg-rose-50/70 p-2 dark:border-rose-400/30 dark:bg-rose-950/35">
                                                                    <form method="POST" action="{{ route('invoices.payments.adjustments.reverse', [$invoice, $payment]) }}">
                                                                        @csrf
                                                                        <x-danger-button type="submit" class="w-full px-2 py-1 text-[11px] normal-case tracking-normal">
                                                                            <span class="flex flex-col items-center leading-tight">
                                                                                <span>Confirm</span>
                                                                                <span>reverse</span>
                                                                                <span>entry</span>
                                                                            </span>
                                                                        </x-danger-button>
                                                                    </form>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="w-28"
                                                             x-data="{
                                                                 open: @js($showCorrectionMenu),
                                                                 panelStyle: {},
                                                                 init() {
                                                                     const syncPanelPosition = () => this.repositionPanel();
                                                                     this.$watch('open', (value) => {
                                                                         if (value) {
                                                                             this.$nextTick(() => this.repositionPanel());
                                                                         }
                                                                     });
                                                                     window.addEventListener('resize', syncPanelPosition);
                                                                     window.addEventListener('scroll', syncPanelPosition, true);
                                                                     if (this.open) {
                                                                         this.$nextTick(() => this.repositionPanel());
                                                                     }
                                                                 },
                                                                 togglePanel() {
                                                                     this.open = !this.open;
                                                                     if (this.open) {
                                                                         this.$nextTick(() => this.repositionPanel());
                                                                     }
                                                                 },
                                                                 repositionPanel() {
                                                                     if (!this.open || !this.$refs.trigger) {
                                                                         return;
                                                                     }
                                                                     const margin = 16;
                                                                     const gap = 8;
                                                                     const triggerRect = this.$refs.trigger.getBoundingClientRect();
                                                                     const panelRect = this.$refs.panel?.getBoundingClientRect();
                                                                     const panelWidth = Math.min(panelRect?.width || 288, window.innerWidth - (margin * 2));
                                                                     let left = triggerRect.right - panelWidth;
                                                                     left = Math.max(margin, Math.min(left, window.innerWidth - margin - panelWidth));
                                                                     const panelHeight = panelRect?.height || 0;
                                                                     const maxHeight = Math.max(160, window.innerHeight - (margin * 2));
                                                                     let top = triggerRect.bottom + gap;
                                                                     if (panelHeight && top + panelHeight > window.innerHeight - margin) {
                                                                         const aboveTop = triggerRect.top - gap - panelHeight;
                                                                         top = aboveTop >= margin
                                                                             ? aboveTop
                                                                             : Math.max(margin, window.innerHeight - margin - panelHeight);
                                                                     }
                                                                     this.panelStyle = {
                                                                         position: 'fixed',
                                                                         left: `${left}px`,
                                                                         top: `${top}px`,
                                                                         width: `${panelWidth}px`,
                                                                         maxHeight: `${maxHeight}px`,
                                                                     };
                                                                 },
                                                             }"
                                                             @keydown.escape.window="open = false">
                                                            <button type="button"
                                                                    x-ref="trigger"
                                                                    class="inline-flex w-full items-center justify-center gap-1 rounded-md border px-3 py-2 text-center text-xs font-semibold leading-tight shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $correctionButtonClasses }}"
                                                                    x-on:click="togglePanel()"
                                                                    x-bind:aria-expanded="open ? 'true' : 'false'"
                                                                    aria-haspopup="dialog"
                                                                    aria-controls="payment-correction-panel-{{ $payment->id }}">
                                                                <span class="sr-only">Correction menu: {{ $correctionLabelText }}</span>
                                                                <span class="flex flex-col items-center">
                                                                    @foreach ($correctionLabelLines as $labelLine)
                                                                        <span>{{ $labelLine }}</span>
                                                                    @endforeach
                                                                </span>
                                                                <svg viewBox="0 0 12 12"
                                                                     aria-hidden="true"
                                                                     class="h-3 w-3 shrink-0 transition-transform"
                                                                     x-bind:class="open ? 'rotate-90' : ''">
                                                                    <path d="M4 2.5 8 6 4 9.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" />
                                                                </svg>
                                                            </button>
                                                            <template x-teleport="body">
                                                                <div id="payment-correction-panel-{{ $payment->id }}"
                                                                     x-ref="panel"
                                                                     x-cloak
                                                                     x-show="open"
                                                                     x-transition.origin.top.right
                                                                     @click.outside="open = false"
                                                                     x-bind:style="panelStyle"
                                                                     class="z-50 space-y-2 overflow-y-auto rounded-lg border p-3 text-xs text-gray-700 shadow-xl dark:text-slate-100 {{ $correctionPanelClasses }}">
                                                                    @if (! $isSourcePayment)
                                                                        <div class="space-y-2">
                                                                            <p class="font-semibold text-emerald-900 dark:text-emerald-100">Applied here through reattribution.</p>
                                                                            <p>Counts on this invoice.</p>
                                                                            @if ($relatedSourceInvoice)
                                                                                <p>
                                                                                    Source invoice:
                                                                                    <a href="{{ route('invoices.show', $relatedSourceInvoice) }}" class="font-semibold text-indigo-700 hover:text-indigo-900 dark:text-indigo-200 dark:hover:text-indigo-100">
                                                                                        {{ $relatedSourceInvoice->number }}
                                                                                    </a>
                                                                                </p>
                                                                            @endif
                                                                            @if ($canUndoReattribution)
                                                                                <form method="POST"
                                                                                      action="{{ route('invoices.payments.undo-reattribution', [$correctionRouteInvoice, $payment]) }}"
                                                                                      class="rounded-lg border-2 border-emerald-300 bg-emerald-50 p-3 dark:border dark:border-emerald-400/30 dark:bg-emerald-950/35">
                                                                                    @csrf
                                                                                    @method('PATCH')
                                                                                    <div class="flex items-center gap-3">
                                                                                        <p class="flex-1 text-xs text-emerald-900 dark:text-emerald-100">
                                                                                            Return this payment to {{ $correctionRouteInvoice->number }}.
                                                                                        </p>
                                                                                        <x-secondary-button type="submit" class="border-2 border-emerald-400 px-3 py-1 text-xs normal-case tracking-normal text-emerald-900 hover:border-emerald-500 hover:bg-emerald-100">
                                                                                            Undo reattribution
                                                                                        </x-secondary-button>
                                                                                    </div>
                                                                                </form>
                                                                            @endif
                                                                        </div>
                                                                    @elseif ($payment->isIgnored())
                                                                        <div class="space-y-2">
                                                                            <div>
                                                                                <p class="font-semibold text-amber-900 dark:text-amber-100">Ignored for invoice math.</p>
                                                                                <p class="mt-1">{{ $payment->confirmed_at ? 'Confirmed row excluded from totals.' : 'Pending row excluded from totals.' }}</p>
                                                                                <p class="mt-1">Reason: {{ $payment->ignore_reason }}</p>
                                                                                <p class="mt-1">Ignored {{ optional($payment->ignored_at)->toDayDateTimeString() ?? '—' }}</p>
                                                                            </div>
                                                                            <form method="POST"
                                                                                  action="{{ route('invoices.payments.restore', [$invoice, $payment]) }}"
                                                                                  class="space-y-2 rounded-lg border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-400/30 dark:bg-indigo-950/35">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <p class="text-xs text-indigo-900 dark:text-indigo-100">
                                                                                    <button type="submit" class="font-semibold text-indigo-700 underline decoration-indigo-400 underline-offset-2 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-200 dark:hover:text-indigo-100 dark:focus:ring-indigo-400 dark:focus:ring-offset-slate-900">
                                                                                        Restore
                                                                                    </button>
                                                                                    this payment so it counts toward invoice totals and status again.
                                                                                </p>
                                                                            </form>
                                                                        </div>
                                                                    @else
                                                                        <div class="space-y-2">
                                                                            @if ($isOutgoingReattribution)
                                                                                <div>
                                                                                    <p class="font-semibold text-sky-900 dark:text-sky-100">Reapplied elsewhere.</p>
                                                                                    <p class="mt-1">No longer counts on this invoice.</p>
                                                                                    <p class="mt-1">
                                                                                        Counting on
                                                                                        @if ($relatedDestinationInvoice)
                                                                                            <a href="{{ route('invoices.show', $relatedDestinationInvoice) }}" class="font-semibold text-indigo-700 hover:text-indigo-900 dark:text-indigo-200 dark:hover:text-indigo-100">
                                                                                                {{ $relatedDestinationInvoice->number }}
                                                                                            </a>
                                                                                        @else
                                                                                            another invoice
                                                                                        @endif
                                                                                    </p>
                                                                                    @if ($payment->reattribute_reason)
                                                                                        <p class="mt-1">Reason: {{ $payment->reattribute_reason }}</p>
                                                                                    @endif
                                                                                    <p class="mt-1">Updated {{ optional($payment->reattributed_at)->toDayDateTimeString() ?? '—' }}</p>
                                                                                </div>
                                                                                @if ($canUndoReattribution)
                                                                                    <form method="POST"
                                                                                          action="{{ route('invoices.payments.undo-reattribution', [$correctionRouteInvoice, $payment]) }}"
                                                                                          class="rounded-lg border-2 border-sky-300 bg-sky-50 p-3 dark:border dark:border-sky-400/30 dark:bg-sky-950/35">
                                                                                        @csrf
                                                                                        @method('PATCH')
                                                                                        <div class="flex items-center gap-3">
                                                                                            <p class="flex-1 text-xs text-sky-900 dark:text-sky-100">
                                                                                                Return this payment to {{ $invoice->number }}.
                                                                                            </p>
                                                                                            <x-secondary-button type="submit" class="border-2 border-sky-400 px-3 py-1 text-xs normal-case tracking-normal text-sky-900 hover:border-sky-500 hover:bg-sky-100">
                                                                                                Undo reattribution
                                                                                            </x-secondary-button>
                                                                                        </div>
                                                                                    </form>
                                                                                @endif
                                                                            @endif
                                                                            <details @if ($showReattributeForm) open @endif>
                                                                                <summary class="list-none cursor-pointer rounded-md border border-indigo-200 bg-white px-3 py-2 text-center text-xs font-semibold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-400/35 dark:bg-slate-900/80 dark:text-indigo-200 dark:hover:border-indigo-300/60 dark:hover:bg-indigo-950/35 dark:focus:ring-indigo-400 dark:focus:ring-offset-slate-900 [&::-webkit-details-marker]:hidden">
                                                                                    {{ $isOutgoingReattribution ? 'Change reattribution' : 'Reattribute payment' }}
                                                                                </summary>
                                                                                <form method="POST"
                                                                                      action="{{ route('invoices.payments.reattribute', [$invoice, $payment]) }}"
                                                                                      class="mt-2 space-y-2 rounded-lg border border-indigo-100 bg-indigo-50/70 p-3 dark:border-indigo-400/30 dark:bg-indigo-950/35">
                                                                                    @csrf
                                                                                    @method('PATCH')
                                                                                    <input type="hidden" name="correction_payment_id" value="{{ $payment->id }}">
                                                                                    <p class="text-xs text-indigo-900 dark:text-indigo-100">
                                                                                        Stop counting this payment toward {{ $invoice->number }} and count it toward another invoice.
                                                                                    </p>
                                                                                    <div>
                                                                                        <label for="destination_invoice_id_{{ $payment->id }}" class="text-xs font-semibold text-indigo-900 dark:text-indigo-100">Destination invoice</label>
                                                                                        <select id="destination_invoice_id_{{ $payment->id }}"
                                                                                                name="destination_invoice_id"
                                                                                                class="mt-1 w-full rounded text-sm dark:bg-slate-900/80 dark:text-slate-100 {{ $showReattributeForm && $errors->has('destination_invoice_id') ? 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-400/60' : 'border-indigo-200 dark:border-indigo-400/30' }}">
                                                                                            @unless ($isOutgoingReattribution)
                                                                                                <option value="" @selected($selectedDestinationId === null)>Select an invoice</option>
                                                                                            @endunless
                                                                                            @foreach ($reattributeDestinations as $destinationInvoice)
                                                                                                <option value="{{ $destinationInvoice->id }}" @selected((string) $selectedDestinationId === (string) $destinationInvoice->id)>
                                                                                                    {{ $destinationInvoice->number }}
                                                                                                    @if ($destinationInvoice->client?->name)
                                                                                                        — {{ $destinationInvoice->client->name }}
                                                                                                    @endif
                                                                                                    ({{ ucfirst($destinationInvoice->status) }})
                                                                                                </option>
                                                                                            @endforeach
                                                                                        </select>
                                                                                        @if ($showReattributeForm && $errors->has('destination_invoice_id'))
                                                                                            <p class="mt-1 text-xs text-red-700">{{ $errors->first('destination_invoice_id') }}</p>
                                                                                        @endif
                                                                                    </div>
                                                                                    <div>
                                                                                        <label for="reattribute_reason_{{ $payment->id }}" class="text-xs font-semibold text-indigo-900 dark:text-indigo-100">Reason</label>
                                                                                        <textarea id="reattribute_reason_{{ $payment->id }}"
                                                                                                  name="reattribute_reason"
                                                                                                  rows="2"
                                                                                                  class="mt-1 w-full rounded text-sm dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-400 {{ $showReattributeForm && $errors->has('reattribute_reason') ? 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-400/60' : 'border-indigo-200 dark:border-indigo-400/30' }}"
                                                                                                  placeholder="Why should this payment count toward another invoice?">{{ $showReattributeForm ? old('reattribute_reason') : ($isOutgoingReattribution ? $payment->reattribute_reason : '') }}</textarea>
                                                                                        @if ($showReattributeForm && $errors->has('reattribute_reason'))
                                                                                            <p class="mt-1 text-xs text-red-700">{{ $errors->first('reattribute_reason') }}</p>
                                                                                        @endif
                                                                                    </div>
                                                                                    <div class="flex justify-end">
                                                                                        <x-secondary-button type="submit" class="px-3 py-1 text-xs normal-case tracking-normal">
                                                                                            Confirm reattribution
                                                                                        </x-secondary-button>
                                                                                    </div>
                                                                                </form>
                                                                            </details>
                                                                            @if ($canIgnorePayment)
                                                                                <details @if ($showIgnoreForm) open @endif>
                                                                                    <summary class="list-none cursor-pointer rounded-md border border-red-200 bg-white px-3 py-2 text-center text-xs font-semibold text-red-700 shadow-sm transition hover:border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:border-red-400/35 dark:bg-slate-900/80 dark:text-red-200 dark:hover:border-red-300/60 dark:hover:bg-red-950/35 dark:focus:ring-red-400 dark:focus:ring-offset-slate-900 [&::-webkit-details-marker]:hidden">
                                                                                        Ignore payment
                                                                                    </summary>
                                                                                    <form method="POST"
                                                                                          action="{{ route('invoices.payments.ignore', [$invoice, $payment]) }}"
                                                                                          class="mt-2 space-y-2 rounded-lg border border-red-100 bg-red-50/70 p-3 dark:border-red-400/30 dark:bg-red-950/35">
                                                                                        @csrf
                                                                                        @method('PATCH')
                                                                                        <input type="hidden" name="correction_payment_id" value="{{ $payment->id }}">
                                                                                        <p class="text-xs text-red-900 dark:text-red-100">
                                                                                            This removes the payment from invoice totals and status without deleting the raw ledger row.
                                                                                        </p>
                                                                                        <div>
                                                                                            <label for="ignore_reason_{{ $payment->id }}" class="text-xs font-semibold text-red-900 dark:text-red-100">Reason</label>
                                                                                            <textarea id="ignore_reason_{{ $payment->id }}"
                                                                                                      name="ignore_reason"
                                                                                                      rows="2"
                                                                                                      class="mt-1 w-full rounded text-sm dark:bg-slate-900/80 dark:text-slate-100 dark:placeholder:text-slate-400 {{ $showIgnoreForm && $errors->has('ignore_reason') ? 'border-red-300 focus:border-red-500 focus:ring-red-500 dark:border-red-400/60' : 'border-red-200 dark:border-red-400/30' }}"
                                                                                                      placeholder="Why should this payment stop counting toward this invoice?">{{ $showIgnoreForm ? old('ignore_reason') : '' }}</textarea>
                                                                                            @if ($showIgnoreForm)
                                                                                                <p class="mt-1 text-xs font-semibold text-red-700 underline decoration-2 decoration-red-500 underline-offset-2 animate-pulse">{{ $errors->first('ignore_reason') }}</p>
                                                                                            @endif
                                                                                        </div>
                                                                                        <div class="flex justify-end">
                                                                                            <x-danger-button type="submit" class="px-3 py-1 text-xs normal-case tracking-normal">
                                                                                                Confirm ignore
                                                                                            </x-danger-button>
                                                                                        </div>
                                                                                    </form>
                                                                                </details>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </template>
                                                        </div>
                                                    @endif
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
            <div id="public-link-card" class="invoice-anchor-target rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Public link</h3>
                    @if ($invoice->public_enabled && $invoice->public_url)
                        <div class="flex items-center gap-2">
                            <form action="{{ route('invoices.share.rotate', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Regenerate public link? Old URL will stop working.');">
                                @csrf @method('PATCH')
                                @if ($gettingStartedContext)
                                    <input type="hidden" name="getting_started" value="1">
                                @endif
                                <x-secondary-button type="submit">Rotate link</x-secondary-button>
                            </form>

                            <form action="{{ route('invoices.share.disable', $invoice) }}" method="POST"
                                  onsubmit="return confirm('Disable public link?');">
                                @csrf @method('PATCH')
                                @if ($gettingStartedContext)
                                    <input type="hidden" name="getting_started" value="1">
                                @endif
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
                            <x-secondary-button type="button" data-copy-text="{{ $invoice->public_url }}">📋 Copy</x-secondary-button>
                            <a href="{{ $invoice->public_url }}" target="_blank" rel="noopener"
                               class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Open
                            </a>
                        </div>
                        @php
                            $isPublicLinkExpired = $invoice->public_expires_at && $invoice->public_expires_at->isPast();
                        @endphp
                        @if ($invoice->public_expires_at)
                            @if ($isPublicLinkExpired)
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
                        @if ($showSinglePaymentGuidance)
                            @if ($isPublicLinkExpired)
                                <div class="mt-4">
                                    <p class="text-xs text-amber-700">
                                        Tip: remind the client to send the full balance in a single Bitcoin transaction if possible when you share this link.
                                        Multiple payments are accepted, but splitting often increases miner fees.
                                    </p>
                                </div>
                            @else
                                <p class="mt-2 text-xs text-amber-700">
                                    Tip: remind the client to send the full balance in a single Bitcoin transaction if possible when you share this link.
                                    Multiple payments are accepted, but splitting often increases miner fees.
                                </p>
                            @endif
                        @endif
                    </div>
                @else
                    <form action="{{ route('invoices.share.enable', $invoice) }}"
                          method="POST"
                          class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end"
                          onsubmit="const i=this.querySelector('input[name=_scroll_y]'); if(i){ i.value=Math.round(window.scrollY || window.pageYOffset || 0); }">
                        @csrf @method('PATCH')
                        @if ($gettingStartedContext)
                            <input type="hidden" name="getting_started" value="1">
                        @endif
                        <input type="hidden" name="_scroll_y" value="">

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
                            <p class="mt-1 text-[14.67px] text-gray-500">If set, this overrides the preset.</p>
                        </div>

                        <div>
                            <x-primary-button
                                class="w-full sm:w-auto {{ $gettingStartedContext ? $onboardingGlow : '' }}"
                                :data-getting-started-highlight="$gettingStartedContext ? 'deliver-enable-public-link' : null">
                                {{ $gettingStartedContext ? $gettingStartedMarker . ' Enable public link' : 'Enable public link' }}
                            </x-primary-button>
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
                                @if ($gettingStartedContext)
                                    <input type="hidden" name="getting_started" value="1">
                                @endif
                                <button type="submit" class="underline text-red-600 hover:text-red-700">disable the public link</button>
                            </form>
                            <span>.</span>
                        </div>
                    </div>
                @endif
            </div>
            </div>

            <div id="send-invoice-email-card" class="invoice-anchor-target rounded-lg bg-white p-6 shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700">Send invoice email</h3>
                        <p class="text-xs text-gray-500">Emails include the public share link, summary, and optional note.</p>
                        <p class="mt-1 text-xs font-medium text-gray-700">To: {{ $invoice->client->email ?? '—' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('invoices.deliver', $invoice) }}" class="mt-3 space-y-3"
                      data-delivery-message-form
                      data-delivery-draft-url="{{ route('invoices.deliver.draft', $invoice) }}">
                    @csrf
                    @if ($gettingStartedContext)
                        <input type="hidden" name="getting_started" value="1">
                    @endif
                    <textarea name="message" rows="2" class="w-full rounded border-gray-300 text-sm"
                              data-delivery-message-input
                              placeholder="Optional note to include in the email">{{ old('message', $invoice->delivery_message_draft ?? '') }}</textarea>
                    <p class="text-xs text-gray-500" data-delivery-message-save-state></p>
                    @error('message')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="cc_self" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                               @checked(old('cc_self'))>
                        CC myself
                    </label>
                    <div>
                        @if (!$invoice->public_enabled)
                            <p class="mb-2 text-xs text-red-600">Enable public link first.</p>
                        @elseif (!$invoice->client || empty($invoice->client->email))
                            <p class="mb-2 text-xs text-red-600">Add a client email first.</p>
                        @endif
                        <x-primary-button
                            type="submit"
                            :disabled="!$canDeliver"
                            class="{{ $gettingStartedContext ? $onboardingGlow : '' }}"
                            :data-getting-started-highlight="$gettingStartedContext ? 'deliver-send-invoice' : null">
                            {{ $gettingStartedContext ? $gettingStartedMarker . ' Send invoice' : 'Send invoice' }}
                        </x-primary-button>
                    </div>
                </form>
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
            const correctionFocusFieldId = @json(session('correction_focus_field'));
            const correctionFocusRowId = @json(session('correction_focus_row'));
            const hasHashTarget = typeof window.location.hash === 'string' && window.location.hash.length > 1;
            const restoreScrollY = Number(@json(session('restore_scroll_y')));
            const stickyNav = document.querySelector('[data-invoice-sticky-nav]');
            const hashLinks = document.querySelectorAll('a[href^="#"]');

            const scrollToInvoiceAnchor = (target, behavior = 'auto') => {
                if (!target) {
                    return;
                }

                const stickyNavStyles = stickyNav ? window.getComputedStyle(stickyNav) : null;
                const stickyNavTop = stickyNavStyles ? parseFloat(stickyNavStyles.top || '0') || 0 : 0;
                const stickyNavHeight = stickyNav ? stickyNav.getBoundingClientRect().height : 0;
                const offset = stickyNavTop + stickyNavHeight + 20;
                const targetTop = window.scrollY + target.getBoundingClientRect().top - offset;

                window.scrollTo({
                    top: Math.max(targetTop, 0),
                    left: 0,
                    behavior,
                });
            };

            if (!hasHashTarget && !correctionFocusFieldId && !correctionFocusRowId && Number.isFinite(restoreScrollY) && restoreScrollY >= 0) {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        window.scrollTo({ top: restoreScrollY, left: 0, behavior: 'auto' });
                    });
                });
            }

            hashLinks.forEach((link) => {
                link.addEventListener('click', (event) => {
                    const href = link.getAttribute('href');
                    if (!href || href === '#') {
                        return;
                    }

                    const target = document.querySelector(href);
                    if (!target) {
                        return;
                    }

                    event.preventDefault();
                    history.pushState(null, '', href);
                    requestAnimationFrame(() => scrollToInvoiceAnchor(target, 'smooth'));
                });
            });

            if (hasHashTarget) {
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        const target = document.querySelector(window.location.hash);
                        scrollToInvoiceAnchor(target);
                    });
                });
            }

            if (correctionFocusFieldId || correctionFocusRowId) {
                let correctionFocusAttempts = 0;

                const focusCorrectionTarget = () => {
                    correctionFocusAttempts += 1;

                    const correctionRow = correctionFocusRowId ? document.getElementById(correctionFocusRowId) : null;
                    if (correctionRow) {
                        scrollToInvoiceAnchor(correctionRow);
                    }

                    const correctionField = correctionFocusFieldId ? document.getElementById(correctionFocusFieldId) : null;
                    if (correctionField && correctionField.offsetParent !== null) {
                        correctionField.focus({ preventScroll: true });
                        if (typeof correctionField.setSelectionRange === 'function') {
                            const valueLength = correctionField.value?.length ?? 0;
                            correctionField.setSelectionRange(valueLength, valueLength);
                        }
                        if (correctionRow) {
                            scrollToInvoiceAnchor(correctionRow);
                        }
                        return;
                    }

                    if (correctionFocusAttempts < 24) {
                        requestAnimationFrame(focusCorrectionTarget);
                    }
                };

                requestAnimationFrame(focusCorrectionTarget);
            }

            const deliveryForm = document.querySelector('[data-delivery-message-form]');
            const deliveryInput = deliveryForm?.querySelector('[data-delivery-message-input]');
            const saveState = deliveryForm?.querySelector('[data-delivery-message-save-state]');
            const draftUrl = deliveryForm?.getAttribute('data-delivery-draft-url');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (deliveryForm && deliveryInput && saveState && draftUrl && csrfToken) {
                let lastSavedValue = deliveryInput.value;

                const setSaveState = (text, isError = false) => {
                    saveState.textContent = text;
                    saveState.classList.toggle('text-red-600', isError);
                    saveState.classList.toggle('text-green-600', !isError && text.length > 0);
                };

                const saveDraft = async () => {
                    const currentValue = deliveryInput.value;
                    if (currentValue === lastSavedValue) {
                        return;
                    }

                    setSaveState('Saving...');

                    try {
                        const response = await fetch(draftUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ message: currentValue }),
                        });

                        if (!response.ok) {
                            throw new Error('save-failed');
                        }

                        lastSavedValue = currentValue;
                        setSaveState('Saved');
                        setTimeout(() => {
                            if (saveState.textContent === 'Saved') {
                                setSaveState('');
                            }
                        }, 1200);
                    } catch {
                        setSaveState('Could not save this note yet.', true);
                    }
                };

                deliveryInput.addEventListener('change', saveDraft);
            }

            const syncPaymentNoteFieldHeight = (noteField) => {
                const noteCell = noteField.closest('td');
                const noteContainer = noteField.closest('[data-payment-note-container]');

                if (!noteCell || !noteContainer) {
                    return;
                }

                noteField.style.height = 'auto';

                const cellStyles = window.getComputedStyle(noteCell);
                const containerStyles = window.getComputedStyle(noteContainer);
                const gap = Number.parseFloat(containerStyles.rowGap || containerStyles.gap || '0') || 0;
                const cellInnerHeight = noteCell.getBoundingClientRect().height
                    - (Number.parseFloat(cellStyles.paddingTop || '0') || 0)
                    - (Number.parseFloat(cellStyles.paddingBottom || '0') || 0);

                let siblingHeight = 0;
                let visibleSiblingCount = 0;

                Array.from(noteContainer.children).forEach((child) => {
                    if (child === noteField || child.offsetParent === null) {
                        return;
                    }

                    siblingHeight += child.getBoundingClientRect().height;
                    visibleSiblingCount += 1;
                });

                const contentHeight = Math.ceil(noteField.scrollHeight);
                const availableHeight = Math.floor(cellInnerHeight - siblingHeight - (visibleSiblingCount * gap));
                const targetHeight = Math.max(88, contentHeight, availableHeight);

                noteField.style.height = `${targetHeight}px`;
            };

            document.querySelectorAll('[data-payment-note-form]').forEach((noteForm) => {
                const noteInput = noteForm.querySelector('[data-payment-note-input]');
                const noteSaveState = noteForm.querySelector('[data-payment-note-save-state]');
                const noteDisplay = noteForm.closest('td')?.querySelector('[data-payment-note-display]');

                if (!noteInput || !noteSaveState || !csrfToken) {
                    return;
                }

                let lastSavedValue = noteInput.value;

                const setNoteSaveState = (text, isError = false) => {
                    noteSaveState.textContent = text;
                    noteSaveState.classList.toggle('text-red-600', isError);
                    noteSaveState.classList.toggle('text-green-600', !isError && text.length > 0);
                    noteSaveState.classList.toggle('text-gray-500', text.length === 0);
                    requestAnimationFrame(() => syncPaymentNoteFieldHeight(noteInput));
                };

                const saveNote = async () => {
                    const currentValue = noteInput.value;

                    if (currentValue === lastSavedValue) {
                        return;
                    }

                    setNoteSaveState('Saving...');

                    try {
                        const response = await fetch(noteForm.action, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                note: currentValue,
                                source_payment_id: noteForm.querySelector('input[name="source_payment_id"]')?.value,
                            }),
                        });

                        if (response.status === 422) {
                            const payload = await response.json();
                            throw new Error(payload?.errors?.note?.[0] || 'Could not save this note yet.');
                        }

                        if (!response.ok) {
                            throw new Error('Could not save this note yet.');
                        }

                        const payload = await response.json();

                        lastSavedValue = currentValue;
                        if (noteDisplay) {
                            noteDisplay.textContent = payload.note && payload.note.length > 0 ? payload.note : '—';
                        }

                        setNoteSaveState('Saved');
                        setTimeout(() => {
                            if (noteSaveState.textContent === 'Saved') {
                                setNoteSaveState('');
                            }
                        }, 1200);
                    } catch (error) {
                        setNoteSaveState(error instanceof Error ? error.message : 'Could not save this note yet.', true);
                    }
                };

                syncPaymentNoteFieldHeight(noteInput);
                requestAnimationFrame(() => syncPaymentNoteFieldHeight(noteInput));
                window.addEventListener('load', () => syncPaymentNoteFieldHeight(noteInput), { once: true });
                window.addEventListener('resize', () => syncPaymentNoteFieldHeight(noteInput));

                const noteCell = noteInput.closest('td');
                if (noteCell && 'ResizeObserver' in window) {
                    const resizeObserver = new ResizeObserver(() => syncPaymentNoteFieldHeight(noteInput));
                    resizeObserver.observe(noteCell);
                }

                noteInput.addEventListener('input', () => syncPaymentNoteFieldHeight(noteInput));
                noteInput.addEventListener('change', saveNote);
            });

            document.querySelectorAll('textarea[data-payment-note-field]:not([data-payment-note-input])').forEach((noteField) => {
                syncPaymentNoteFieldHeight(noteField);
                requestAnimationFrame(() => syncPaymentNoteFieldHeight(noteField));
                window.addEventListener('load', () => syncPaymentNoteFieldHeight(noteField), { once: true });
                window.addEventListener('resize', () => syncPaymentNoteFieldHeight(noteField));
                noteField.addEventListener('focus', () => requestAnimationFrame(() => syncPaymentNoteFieldHeight(noteField)));
                noteField.addEventListener('click', () => requestAnimationFrame(() => syncPaymentNoteFieldHeight(noteField)));

                const noteCell = noteField.closest('td');
                if (noteCell && 'ResizeObserver' in window) {
                    const resizeObserver = new ResizeObserver(() => syncPaymentNoteFieldHeight(noteField));
                    resizeObserver.observe(noteCell);
                }
            });

            const padCompactDatePart = (value) => String(value).padStart(2, '0');

            document.querySelectorAll('[data-utc-compact-ts]').forEach((node) => {
                const iso = node.getAttribute('data-utc-compact-ts');
                if (!iso) return;

                const parsed = new Date(iso);
                if (Number.isNaN(parsed.getTime())) return;

                const compact = [
                    padCompactDatePart(parsed.getMonth() + 1),
                    padCompactDatePart(parsed.getDate()),
                    padCompactDatePart(parsed.getFullYear() % 100),
                ].join('-') + ` ${padCompactDatePart(parsed.getHours())}:${padCompactDatePart(parsed.getMinutes())}`;

                node.textContent = compact;

                const localizedTitle = parsed.toLocaleString(undefined, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                });

                if (localizedTitle) {
                    node.title = localizedTitle;
                }
            });

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
