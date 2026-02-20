<div class="box unavailable-box" data-public-unavailable="true">
    @if (!empty($billingDetails['heading']))
        <div class="muted" style="text-transform:uppercase; letter-spacing:0.2em; font-size:12px; margin-bottom:4px;">
            {{ $billingDetails['heading'] }}
        </div>
    @endif

    <h1>
        Invoice <span class="muted">#{{ $invoice->number }}</span>
    </h1>

    <h2 style="margin:8px 0 10px; font-size:18px; color:#b91c1c;">This public payment link is no longer active</h2>

    <p style="margin:0 0 8px; font-size:14px; color:#7f1d1d;">
        This invoice link has been disabled or expired. For assistance, please contact
        <strong>{{ $billingDetails['name'] ?? $invoice->user->name }}</strong>
        @if(!empty($billingDetails['email']))
            via <a href="mailto:{{ $billingDetails['email'] }}" style="color:#1d4ed8;">{{ $billingDetails['email'] }}</a>
        @endif
        @if(!empty($billingDetails['phone']))
            {{ empty($billingDetails['email']) ? 'at' : 'or' }} {{ $billingDetails['phone'] }}
        @endif
        .
    </p>

    <p class="muted" style="margin:0; font-size:13px;">
        Please request a fresh payment link before sending funds.
    </p>
</div>
