<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @if (!empty($public))
        <meta name="robots" content="noindex,nofollow,noarchive">
    @endif

    @php
        $iconSymbol = !empty($public) ? '🌐' : '💸';
        $printFavicon = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><text x="50%" y="60%" font-size="36" text-anchor="middle">' . $iconSymbol . '</text></svg>');
    @endphp
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ $printFavicon }}">

    <style>
        :root { --gray:#6b7280; --light:#e5e7eb; --dark:#111827; }
        * { box-sizing: border-box; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, "Apple Color Emoji","Segoe UI Emoji"; color: var(--dark); background:#fff; margin:0; }
        .container { max-width: 800px; margin: 0 auto; padding: 24px; }
        h1 { margin: 0 0 4px; font-size: 24px; }
        .muted { color: var(--gray); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .box { border: 1px solid var(--light); border-radius: 12px; padding: 16px; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 6px; border-bottom: 1px solid var(--light); text-align: left; font-size: 14px; }
        th { text-transform: uppercase; font-size: 12px; letter-spacing: .03em; color: var(--gray); }
        .total { font-weight: 700; }
        .badge { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:12px; }
        .badge-paid { background:#dcfce7; color:#166534; }
        .badge-sent { background:#dbeafe; color:#1e40af; }
        .badge-void { background:#fef9c3; color:#92400e; }
        .badge-draft { background:#f3f4f6; color:#374151; }
        .no-print { text-align:right; margin-bottom: 12px; }
        .btn { border:1px solid var(--light); padding:8px 12px; border-radius:8px; background:#fff; cursor:pointer; }
        .paid-watermark {
            position: fixed;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
        }
        .paid-watermark span {
            font-size: clamp(48px, 12vw, 140px);
            font-weight: 900;
            letter-spacing: 0.2em;
            color: rgba(220, 38, 38, 0.15);
            transform: rotate(-18deg);
            text-transform: uppercase;
        }
        .container { position: relative; z-index: 1; }
        @media print {
            .no-print { display:none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
@php
    $st = $invoice->status ?? 'draft';
    $summary = $paymentSummary ?? [
        'expected_usd' => null,
        'expected_btc_formatted' => null,
        'expected_sats' => null,
        'received_usd' => 0.0,
        'received_sats' => null,
        'outstanding_usd' => null,
        'outstanding_btc_formatted' => null,
        'outstanding_btc_float' => null,
        'outstanding_sats' => null,
        'last_payment_detected_at' => null,
        'last_payment_confirmed_at' => null,
    ];
    $billingDetails = $billingDetails ?? $invoice->billingDetails();
@endphp
@php
    $linkActive = $link_active ?? true;
@endphp
@if (!empty($public) && $st === 'paid' && $linkActive)
    <div class="paid-watermark">
        <span>Paid</span>
    </div>
@endif
@if (!empty($public) && ! $linkActive)
    <div class="container">
        <div class="box" style="border-color:#fecaca;background:#fff1f2;">
            <h2 style="margin:0 0 8px;font-size:18px;color:#b91c1c;">This invoice is no longer available</h2>
            <p style="margin:0 0 8px;font-size:14px;color:#7f1d1d;">
                The public payment link has been disabled or expired. Please contact
                {{ $billingDetails['name'] ?? $invoice->user->name }}
                @if(!empty($billingDetails['email']))
                    via <a href="mailto:{{ $billingDetails['email'] }}" style="color:#1d4ed8;">{{ $billingDetails['email'] }}</a>
                @endif
                @if(!empty($billingDetails['phone']))
                    {{ empty($billingDetails['email']) ? 'at' : 'or' }} {{ $billingDetails['phone'] }}
                @endif
                for assistance.
            </p>
        </div>
    </div>
@else
<div class="container">

    <div class="no-print">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn" href="{{ route('invoices.show', $invoice) }}" style="margin-left:8px;">Back</a>
    </div>

    <header style="display:flex; align-items:baseline; justify-content:space-between; margin-bottom:16px;">
        <div>
            <h1>Invoice <span class="muted">#{{ $invoice->number }}</span></h1>
            <div class="muted" style="font-size:14px;">Generated {{ now()->toDateString() }}</div>
        </div>
        <span class="badge
        {{ $st==='paid' ? 'badge-paid' : ($st==='sent' ? 'badge-sent' : ($st==='void' ? 'badge-void' : 'badge-draft')) }}">
        {{ strtoupper($st) }}
      </span>
    </header>

    <section class="row" style="margin-bottom:16px;">
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

    <section class="box" style="margin-bottom:16px;">
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

    <section class="box" style="margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:14px;">Description</h3>
        <div style="white-space:pre-line; font-size:14px;">{{ $invoice->description ?: '-' }}</div>
    </section>

    <section class="box" style="margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:14px;">Amounts</h3>
        <table>
            <tr><th>USD</th><td class="total">${{ number_format($invoice->amount_usd, 2) }}</td></tr>
            <tr><th>BTC</th><td>{{ $displayAmountBtc ?? ($invoice->amount_btc ?? '-') }}</td></tr>
            <tr><th>BTC rate (USD/BTC)</th><td>{{ $displayRateUsd ?? ($invoice->btc_rate ?? '-') }}</td></tr>
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
                <div style="display:flex; justify-content:space-between;">
                    <span>Expected</span>
                    <strong>
                        {{ $currency($summary['expected_usd']) }}
                        @if (!empty($summary['expected_btc_formatted']))
                            ({{ $summary['expected_btc_formatted'] }} BTC)
                        @endif
                    </strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Received</span>
                    <strong>{{ $currency($summary['received_usd']) }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-weight:700;">
                    <span>Outstanding balance</span>
                    <span>
                        {{ $currency($summary['outstanding_usd']) }}
                        @if (!empty($summary['outstanding_btc_formatted']))
                            ({{ $summary['outstanding_btc_formatted'] }} BTC)
                        @endif
                    </span>
                </div>
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

    <section class="box" style="margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:14px;">Payment</h3>
        <table>
            <tr>
                <th>BTC address</th>
                <td class="mono">{{ $invoice->payment_address ?: '-' }}</td>
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
                <td class="mono">{{ optional($invoice->payment_detected_at)->toDateTimeString() ?? '—' }}</td>
            </tr>
            <tr>
                <th>Confirmed at</th>
                <td class="mono">{{ optional($invoice->payment_confirmed_at)->toDateTimeString() ?? '—' }}</td>
            </tr>

            @php $bitcoinUri = $displayBitcoinUri ?? $invoice->bitcoin_uri; @endphp

            @if ($bitcoinUri)
                <tr>
                    <th>Payment QR</th>
                    <td>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
                            <div>
                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->margin(0)->generate($bitcoinUri) !!}
                                <div class="muted" style="font-size:12px; margin-top:6px;">Scan with any Bitcoin wallet.</div>
                                <div class="muted" style="font-size:11px; margin-top:4px; line-height:1.3;">
                                    BTC/USD is captured when this page loads. To avoid over/underpayment and additional miner fees,
                                    refresh right before sending payment; printed copies may be stale.
                                </div>
                            </div>

                            <div style="flex:1; text-align:right;">
                                <div style="font-weight:800; font-size:40px; line-height:1; letter-spacing:-0.02em; color:#4f46e5;">
                                    Thank you!
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            @endif

        </table>
    </section>

@if ($invoice->payments->isNotEmpty())
        <section class="box" style="margin-bottom:16px;">
            <h3 style="margin:0 0 8px; font-size:14px;">Payment history</h3>
            <table>
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
        </section>
    @endif

    @php
        $overpayPercent = $invoice->overpaymentPercent();
        $underpayPercent = $invoice->underpaymentPercent();
    @endphp
    @if ($invoice->requiresClientOverpayAlert())
        <div style="border:1px solid #dcfce7; background:#f0fdf4; color:#166534; border-radius:10px; padding:12px; font-size:13px; margin-bottom:16px;">
            This invoice appears overpaid by approximately {{ number_format($overpayPercent, 1) }}%.
            Overpayments are treated as gratuities by default, so please notify the invoice sender if this was a mistake.
        </div>
    @elseif ($invoice->requiresClientUnderpayAlert())
        <div style="border:1px solid #fee2e2; background:#fef2f2; color:#b91c1c; border-radius:10px; padding:12px; font-size:13px; margin-bottom:16px;">
            An outstanding balance of roughly {{ number_format($underpayPercent, 1) }}% remains. Please send the remaining amount or contact the invoice sender for assistance.
        </div>
    @else
        <div style="border:1px solid #fef3c7; background:#fffbeb; color:#92400e; border-radius:10px; padding:12px; font-size:13px; margin-bottom:16px;">
            Overpayments are treated as gratuities by default. If a payment went over in error, please notify us immediately.
        </div>
    @endif

    @if (!empty($billingDetails['footer_note']))
        <div style="border:1px solid #e5e7eb; background:#fff; border-radius:10px; padding:12px; font-size:13px; margin-bottom:16px;">
            {{ $billingDetails['footer_note'] }}
        </div>
    @endif
@endif

</div>
@if ($rateAsOfIso)
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
@endif
</body>
</html>
