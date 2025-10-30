<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->number }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @if (!empty($public))
        <meta name="robots" content="noindex,nofollow,noarchive">
    @endif

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
        @media print {
            .no-print { display:none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="no-print">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn" href="{{ route('invoices.show', $invoice) }}" style="margin-left:8px;">Back</a>
    </div>

    @php $st = $invoice->status ?? 'draft'; @endphp
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
        <h3 style="margin:0 0 8px; font-size:14px;">Description</h3>
        <div style="white-space:pre-line; font-size:14px;">{{ $invoice->description ?: '-' }}</div>
    </section>

    <section class="box" style="margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:14px;">Amounts</h3>
        <table>
            <tr><th>USD</th><td class="total">${{ number_format($invoice->amount_usd, 2) }}</td></tr>
            <tr><th>BTC</th><td>{{ $invoice->amount_btc ?? '-' }}</td></tr>
            <tr><th>BTC rate (USD/BTC)</th><td>{{ $invoice->btc_rate ?? '-' }}</td></tr>
            @if (!empty($rate_as_of))
                <tr><th>Rate as of</th><td class="muted">{{ $rate_as_of->toDateTimeString() }}</td></tr>
            @endif

        </table>
    </section>

    <section class="box" style="margin-bottom:16px;">
        <h3 style="margin:0 0 8px; font-size:14px;">Payment</h3>
        <table>
            <tr>
                <th>BTC address</th>
                <td class="mono">{{ $invoice->btc_address ?: '-' }}</td>
            </tr>
            <tr>
                <th>TXID</th>
                <td class="mono">{{ $invoice->txid ?: '-' }}</td>
            </tr>

            @if (!empty($invoice->bitcoin_uri))
                <tr>
                    <th>Payment QR</th>
                    <td>
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
                            <div>
                                {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(180)->margin(0)->generate($invoice->bitcoin_uri) !!}
                                <div class="muted" style="font-size:12px; margin-top:6px;">Scan with any Bitcoin wallet.</div>
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

</div>
</body>
</html>
