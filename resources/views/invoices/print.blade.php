<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CryptoZing - Invoice: {{ $invoice->number }}</title>
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
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            color: var(--dark);
            background:#fff;
            margin:0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
            position: relative;
            z-index: 1;
        }
        h1 { margin: 0 0 4px; font-size: 24px; }
        .muted { color: var(--gray); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .box { border: 1px solid var(--light); border-radius: 12px; padding: 16px; }
        .section-gap { margin-bottom: 16px; }
        .mono {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 8px 6px;
            border-bottom: 1px solid var(--light);
            text-align: left;
            font-size: 14px;
            vertical-align: top;
            overflow-wrap: anywhere;
        }
        th { text-transform: uppercase; font-size: 12px; letter-spacing: .03em; color: var(--gray); }
        .total { font-weight: 700; }
        .badge { display:inline-block; padding:2px 8px; border-radius:9999px; font-size:12px; }
        .badge-paid { background:#dcfce7; color:#166534; }
        .badge-sent { background:#dbeafe; color:#1e40af; }
        .badge-void { background:#fef9c3; color:#92400e; }
        .badge-draft { background:#f3f4f6; color:#374151; }
        .btn {
            border:1px solid var(--light);
            padding:8px 12px;
            border-radius:8px;
            background:#fff;
            cursor:pointer;
            text-decoration:none;
            color:inherit;
            font-size:14px;
            line-height:1.2;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
        .btn:hover { background:#f9fafb; }
        .no-print {
            display:flex;
            justify-content:flex-end;
            gap:8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .print-header {
            display:flex;
            align-items:baseline;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:16px;
        }
        .table-wrap { width: 100%; overflow-x: auto; }
        .payment-table-wrap { width: 100%; overflow-x: auto; }
        .payment-table th {
            width: 11rem;
            min-width: 11rem;
            white-space: nowrap;
        }
        .payment-table td { min-width: 0; }
        .payment-address-value {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-all;
        }
        .history-table { min-width: 620px; }
        .payment-qr-wrap {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
            flex-wrap: wrap;
        }
        .payment-qr-block {
            flex: 0 0 auto;
            width: 180px;
        }
        .payment-qr-block svg {
            display: block;
            width: 180px;
            height: 180px;
        }
        .payment-qr-rate-note {
            color: var(--gray);
            font-size: 11px;
            line-height: 1.4;
            font-weight: 400;
            text-transform: none;
            letter-spacing: normal;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .payment-qr-rate-note-desktop {
            margin-top: 10px;
            display: block;
        }
        .payment-qr-rate-note-mobile {
            display: none;
        }
        .thank-you-block {
            flex: 1;
            min-width: 180px;
            text-align: right;
        }
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
        .unavailable-box {
            border-color:#fecaca;
            background:#fff1f2;
        }

        @media (max-width: 640px) {
            .container { padding: 14px; }
            .row { grid-template-columns: 1fr; }
            .print-header { align-items: flex-start; }
            .no-print { justify-content: flex-start; }
            .thank-you-block {
                min-width: 100%;
                text-align: left;
            }
            .payment-qr-rate-note-desktop {
                display: none;
            }
            .payment-qr-rate-note-mobile {
                display: block;
                margin-top: 24px;
            }
        }

        @media print {
            .no-print { display:none; }
            body { margin: 0; }
            .container { padding: 16px; }
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
        'confirmed_usd' => 0.0,
        'confirmed_sats' => null,
        'outstanding_usd' => null,
        'outstanding_btc_formatted' => null,
        'outstanding_btc_float' => null,
        'outstanding_sats' => null,
        'last_payment_detected_at' => null,
        'last_payment_confirmed_at' => null,
    ];
    $billingDetails = $billingDetails ?? $invoice->billingDetails();
    $publicMode = !empty($public);
    $publicState = $public_state ?? (($link_active ?? true) ? 'active' : 'disabled_or_expired');
    $linkActive = $publicState === 'active';
@endphp

@if ($publicMode && $st === 'paid' && $linkActive)
    <div class="paid-watermark">
        <span>Paid</span>
    </div>
@endif

<div class="container" data-public-mode="{{ $publicMode ? 'true' : 'false' }}" data-public-state="{{ $publicState }}">
    @include('invoices.partials.print.action-bar', [
        'publicMode' => $publicMode,
        'invoice' => $invoice,
    ])

    @if ($publicMode && !$linkActive)
        @include('invoices.partials.print.unavailable', [
            'invoice' => $invoice,
            'billingDetails' => $billingDetails,
        ])
    @else
        @include('invoices.partials.print.content', [
            'invoice' => $invoice,
            'st' => $st,
            'summary' => $summary,
            'billingDetails' => $billingDetails,
        ])
    @endif
</div>

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
</body>
</html>
