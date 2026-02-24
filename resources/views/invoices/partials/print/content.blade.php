<header class="print-header">
    <div>
        @if (!empty($billingDetails['heading']))
            <div class="muted" style="text-transform:uppercase; letter-spacing:0.2em; font-size:12px; margin-bottom:4px;">
                {{ $billingDetails['heading'] }}
            </div>
        @endif
        <h1>Invoice <span class="muted">#{{ $invoice->number }}</span></h1>
        @php
            $issuedAt = $invoice->invoice_date?->copy()->setTimezone(config('app.timezone'))
                ?? $invoice->created_at?->copy()->setTimezone(config('app.timezone'));
        @endphp
        <div class="muted" style="font-size:14px;">Generated {{ $issuedAt ? $issuedAt->toDateString() : '-' }}</div>
    </div>
    <span class="badge
    {{ $st==='paid' ? 'badge-paid' : ($st==='sent' ? 'badge-sent' : ($st==='void' ? 'badge-void' : 'badge-draft')) }}">
    {{ strtoupper($st) }}
  </span>
</header>

<section class="row section-gap">
    <div class="box">
        <h3 style="margin:0 0 8px; font-size:14px;">Summary</h3>
        <table>
            <tr><th>Invoice date</th><td>{{ optional($invoice->invoice_date)->toDateString() ?: '-' }}</td></tr>
            <tr><th>Due date</th><td>{{ optional($invoice->due_date)->toDateString() ?: '-' }}</td></tr>
            <tr><th>Paid at</th><td>{{ optional($invoice->paid_at)->toDateTimeString() ?: '-' }}</td></tr>
            <tr><th>Status</th><td>{{ ucfirst($st) }}</td></tr>
        </table>
    </div>
    <div class="box">
        <h3 style="margin:0 0 8px; font-size:14px;">Bill To</h3>
        <div style="font-size:14px;">
            <div><strong>{{ $invoice->client->name ?? '-' }}</strong></div>
            <div class="muted">{{ $invoice->client->email ?? '' }}</div>
        </div>
    </div>
</section>

<section class="box section-gap">
    <h3 style="margin:0 0 8px; font-size:14px;">Bill From</h3>
    <div style="font-size:14px;">
        <div><strong>{{ $billingDetails['name'] ?? ($invoice->user->billing_name ?? $invoice->user->name) }}</strong></div>
        @if (!empty($billingDetails['email']))
            <div class="muted"><a href="mailto:{{ $billingDetails['email'] }}" style="color:#4f46e5;">{{ $billingDetails['email'] }}</a></div>
        @endif
        @if (!empty($billingDetails['phone']))
            <div class="muted">{{ $billingDetails['phone'] }}</div>
        @endif
        @if (!empty($billingDetails['address_lines']))
            <div class="muted" style="margin-top:6px; line-height:1.4;">
                @foreach ($billingDetails['address_lines'] as $line)
                    <div>{{ $line }}</div>
                @endforeach
            </div>
        @endif
    </div>
</section>

<section class="box section-gap">
    <h3 style="margin:0 0 8px; font-size:14px;">Description</h3>
    <div style="white-space:pre-line; font-size:14px;">{{ $invoice->description ?: '-' }}</div>
</section>

<section class="box section-gap">
    <h3 style="margin:0 0 8px; font-size:14px;">Amounts</h3>
    <table>
        <tr><th>USD</th><td class="total">${{ number_format($invoice->amount_usd, 2) }}</td></tr>
        <tr><th>BTC</th><td>{{ $displayAmountBtc !== null ? $displayAmountBtc : '—' }}</td></tr>
        <tr><th>BTC rate (USD/BTC)</th><td>{{ $displayRateUsd !== null ? $displayRateUsd : '—' }}</td></tr>
        @php
            $rateAsOfIso = null;
            $rateAsOfFallback = null;
            if (!empty($rate_as_of)) {
                $rateCarbon = $rate_as_of instanceof \Carbon\Carbon
                    ? $rate_as_of
                    : \Carbon\Carbon::parse($rate_as_of);
                if ($rateCarbon) {
                    $rateAsOfIso = $rateCarbon->copy()->utc()->toIso8601String();
                    $rateAsOfFallback = $rateCarbon->copy()
                        ->setTimezone(config('app.timezone'))
                        ->toDayDateTimeString();
                }
            }
        @endphp
        @if ($rateAsOfIso)
            <tr>
                <th>Rate as of</th>
                <td class="muted">
                    <time
                        datetime="{{ $rateAsOfIso }}"
                        data-utc-ts="{{ $rateAsOfIso }}"
                        title="{{ $rateAsOfFallback }}"
                        class="font-medium text-gray-600"
                    >{{ $rateAsOfFallback }}</time>
                </td>
            </tr>
        @endif

    </table>

    @php
        $currency = fn (?float $value) => $value === null ? '—' : ('$' . number_format($value, 2));
    @endphp

    @if (!is_null($summary['expected_usd']))
        <div style="margin-top:12px; border:1px dashed #c7d2fe; border-radius:10px; padding:12px; font-size:13px;">
            <div style="display:flex; justify-content:space-between; gap:12px;">
                <span>Expected</span>
                <strong>
                    {{ $currency($summary['expected_usd']) }}
                    @if (!empty($summary['expected_btc_formatted']))
                        ({{ $summary['expected_btc_formatted'] }} BTC)
                    @endif
                </strong>
            </div>
            <div style="display:flex; justify-content:space-between; gap:12px;">
                <span>Received (detected)</span>
                <strong>{{ $currency($summary['received_usd']) }}</strong>
            </div>
            <div style="display:flex; justify-content:space-between; gap:12px;">
                <span>Confirmed (counts toward status)</span>
                <span style="text-align:right;">
                    <strong>{{ $currency($summary['confirmed_usd']) }}</strong>
                    @php
                        $confirmedBtc = !empty($summary['confirmed_sats'])
                            ? $invoice->formatBitcoinAmount($summary['confirmed_sats'] / \App\Models\Invoice::SATS_PER_BTC)
                            : null;
                    @endphp
                    @if ($confirmedBtc)
                        <div style="font-size:11px; color:#4338ca;">≈ {{ $confirmedBtc }} BTC</div>
                    @endif
                </span>
            </div>
            <div style="display:flex; justify-content:space-between; gap:12px; font-weight:700;">
                <span>Outstanding balance (confirmed)</span>
                <span>
                    {{ $currency($summary['outstanding_usd']) }}
                    @if (!empty($summary['outstanding_btc_formatted']))
                        ({{ $summary['outstanding_btc_formatted'] }} BTC)
                    @endif
                </span>
            </div>
            @if (!empty($summary['outstanding_btc_formatted']))
                <div style="margin-top:6px; font-size:11px; color:#4338ca;">
                    BTC target floats with the latest rate so QR codes always reflect the remaining USD balance.
                </div>
            @endif
        </div>
    @endif

    @php
        $lastDetected = $summary['last_payment_detected_at'] ?? null;
        $lastConfirmed = $summary['last_payment_confirmed_at'] ?? null;
    @endphp

    @if ($lastDetected)
        <div style="margin-top:8px; font-size:12px; color:#4b5563;">
            Last payment detected
            {{ $lastDetected->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }}
            @if ($lastConfirmed)
                (confirmed {{ $lastConfirmed->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }})
            @endif
        </div>
    @endif
</section>

<section class="box section-gap">
    <h3 style="margin:0 0 8px; font-size:14px;">Payment</h3>
    <div class="payment-table-wrap">
        <table class="payment-table">
        <tr>
            <th>BTC address</th>
            <td class="mono payment-address-value">{{ $invoice->payment_address ?: '-' }}</td>
        </tr>
        <tr>
            <th>TXID</th>
            <td class="mono">{{ $invoice->txid ?: '-' }}</td>
        </tr>
        <tr>
            <th>Paid amount (BTC)</th>
            <td class="mono">{{ $invoice->payment_amount_formatted ?? '—' }}</td>
        </tr>
        <tr>
            <th>Confirmations</th>
            <td class="mono">{{ $invoice->payment_confirmations ?? '—' }}</td>
        </tr>
        <tr>
            <th>Confirmation height</th>
            <td class="mono">{{ $invoice->payment_confirmed_height ?? '—' }}</td>
        </tr>
        <tr>
            <th>Detected at</th>
            @php
                $detectedAt = $invoice->payment_detected_at;
                $detectedIso = $detectedAt?->copy()->utc()->toIso8601String();
            @endphp
            <td class="mono">
                @if ($detectedIso)
                    <time data-utc-ts="{{ $detectedIso }}" datetime="{{ $detectedIso }}">
                        {{ $detectedAt->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }}
                    </time>
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <th>Confirmed at</th>
            @php
                $confirmedAt = $invoice->payment_confirmed_at;
                $confirmedIso = $confirmedAt?->copy()->utc()->toIso8601String();
            @endphp
            <td class="mono">
                @if ($confirmedIso)
                    <time data-utc-ts="{{ $confirmedIso }}" datetime="{{ $confirmedIso }}">
                        {{ $confirmedAt->copy()->timezone(config('app.timezone'))->toDayDateTimeString() }}
                    </time>
                @else
                    —
                @endif
            </td>
        </tr>

        @php
            $bitcoinUri = $displayBitcoinUri ?? $invoice->bitcoin_uri;
            $qrRateNote = 'BTC/USD is captured when this page loads. To avoid over/underpayment and additional miner fees, refresh right before sending payment; printed copies may be stale.';
        @endphp

        @if ($bitcoinUri)
            <tr>
                <th>
                    <div>Payment QR</div>
                    <div class="payment-qr-rate-note payment-qr-rate-note-mobile">{{ $qrRateNote }}</div>
                </th>
                <td>
                    <div class="payment-qr-wrap">
                        <div class="payment-qr-block">
                            {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->margin(0)->generate($bitcoinUri) !!}
                            <div class="muted" style="font-size:12px; margin-top:6px;">Scan with any Bitcoin wallet.</div>
                        </div>

                        <div class="thank-you-block">
                            <div style="font-weight:800; font-size:40px; line-height:1; letter-spacing:-0.02em; color:#4f46e5;">
                                Thank you!
                            </div>
                        </div>
                    </div>
                    <div class="payment-qr-rate-note payment-qr-rate-note-desktop">{{ $qrRateNote }}</div>
                </td>
            </tr>
        @endif

        </table>
    </div>

    <div style="margin-top:12px; padding:10px; border-radius:10px; border:1px solid #fed7aa; background:#fff7ed; font-size:13px; line-height:1.4;">
        <strong>Send one payment:</strong> To avoid extra miner fees and processing delays, please send the full outstanding balance in a single Bitcoin transaction. Splitting the invoice across multiple payments can increase costs.
    </div>
</section>

@if ($invoice->payments->isNotEmpty())
    <section class="box section-gap">
        <h3 style="margin:0 0 8px; font-size:14px;">Payment history</h3>
        <div class="table-wrap">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Detected</th>
                        <th>TXID</th>
                        <th style="text-align:right;">BTC</th>
                        <th style="text-align:right;">USD</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice->payments as $payment)
                        <tr>
                            <td>{{ optional($payment->detected_at)->toDayDateTimeString() ?? '—' }}</td>
                            <td class="mono">{{ \Illuminate\Support\Str::limit($payment->txid, 18, '…') }}</td>
                            <td style="text-align:right;">{{ $invoice->formatBitcoinAmount($payment->sats_received / \App\Models\Invoice::SATS_PER_BTC) ?? '—' }}</td>
                            <td style="text-align:right;">
                                @if ($payment->fiat_amount !== null)
                                    <div>${{ number_format($payment->fiat_amount, 2) }}</div>
                                    @if ($payment->usd_rate !== null)
                                        <div style="font-size:11px; color:#6b7280;">
                                            @ ${{ number_format((float) $payment->usd_rate, 2) }} USD/BTC
                                        </div>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($payment->is_adjustment)
                                    {{ $payment->sats_received >= 0 ? 'Manual credit' : 'Manual debit' }}
                                @else
                                    {{ $payment->confirmed_at ? 'Confirmed' : 'Pending' }}
                                @endif
                            </td>
                            <td>{{ $payment->note ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@php
    $overpayPercent = $invoice->overpaymentPercent();
    $underpayPercent = $invoice->underpaymentPercent();
@endphp
@if ($invoice->requiresClientOverpayAlert())
    <div class="section-gap" style="border:1px solid #dcfce7; background:#f0fdf4; color:#166534; border-radius:10px; padding:12px; font-size:13px;">
        This invoice appears overpaid by approximately {{ number_format($overpayPercent, 1) }}%.
        Overpayments are treated as gratuities by default, so please notify the invoice sender if this was a mistake.
    </div>
@elseif ($invoice->requiresClientUnderpayAlert())
    <div class="section-gap" style="border:1px solid #fee2e2; background:#fef2f2; color:#b91c1c; border-radius:10px; padding:12px; font-size:13px;">
        An outstanding balance of roughly {{ number_format($underpayPercent, 1) }}% remains. Please send the remaining amount or contact the invoice sender for assistance.
    </div>
@else
    <div class="section-gap" style="border:1px solid #fef3c7; background:#fffbeb; color:#92400e; border-radius:10px; padding:12px; font-size:13px;">
        Overpayments are treated as gratuities by default. If a payment went over in error, coordinate with your client to refund or apply the surplus as a credit.
    </div>
@endif

@if (!empty($billingDetails['footer_note']))
    <div class="section-gap" style="border:1px solid #e5e7eb; background:#fff; border-radius:10px; padding:12px; font-size:13px;">
        {{ $billingDetails['footer_note'] }}
    </div>
@endif
