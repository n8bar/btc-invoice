<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id','client_id','number','description',
        'amount_usd','btc_rate','amount_btc','payment_address','derivation_index',
        'status','txid','invoice_date','due_date','paid_at',
        'payment_amount_sat','payment_confirmations','payment_confirmed_height',
        'payment_detected_at','payment_confirmed_at',
        'billing_name_override','billing_email_override','billing_phone_override',
        'billing_address_override','invoice_footer_note_override','branding_heading_override',
        'last_overpayment_alert_at','last_underpayment_alert_at',
        'last_past_due_owner_alert_at','last_past_due_client_alert_at',
        'last_partial_warning_sent_at',
    ];

    protected $casts = [
        'amount_usd' => 'decimal:2',
        'amount_btc' => 'decimal:8',
        'btc_rate'   => 'decimal:2', // USD per BTC
        'invoice_date' => 'date',
        'due_date'   => 'date',
        'paid_at'    => 'datetime',
        'public_enabled'    => 'boolean',
        'public_expires_at' => 'datetime',
        'derivation_index' => 'integer',
        'payment_amount_sat' => 'integer',
        'payment_confirmations' => 'integer',
        'payment_confirmed_height' => 'integer',
        'payment_detected_at' => 'datetime',
        'payment_confirmed_at' => 'datetime',
        'last_overpayment_alert_at' => 'datetime',
        'last_underpayment_alert_at' => 'datetime',
        'last_past_due_owner_alert_at' => 'datetime',
        'last_past_due_client_alert_at' => 'datetime',
        'last_partial_warning_sent_at' => 'datetime',
    ];
    public const SATS_PER_BTC = 100_000_000;
    public const PAYMENT_SAT_TOLERANCE = 100;
    public const OVERPAY_USD_TOLERANCE = 10.0;
    public const OVERPAY_PERCENT_TOLERANCE = 1.0;
    public const UNDERPAY_USD_TOLERANCE = 10.0;
    public const UNDERPAY_PERCENT_TOLERANCE = 1.0;
    public const CLIENT_ALERT_PERCENT = 15.0;
    public const SMALL_BALANCE_RESOLUTION_FLOOR_USD = 1.0;
    public const SMALL_BALANCE_RESOLUTION_PERCENT = 0.01; // 1% of expected USD
    public const SMALL_BALANCE_RESOLUTION_CAP_USD = 50.0;

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }
    public function deliveries(): HasMany { return $this->hasMany(InvoiceDelivery::class); }

    public function scopeOwnedBy(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;
        return $query->where('user_id', $userId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'sent', 'partial']);
    }

    public function scopePastDue(Builder $query, ?Carbon $today = null): Builder
    {
        $today = $today ?: Carbon::today(config('app.timezone'));
        return $query->whereIn('status', ['sent', 'partial'])
            ->whereDate('due_date', '<', $today);
    }

    public function scopeDueSoon(Builder $query, ?Carbon $today = null): Builder
    {
        $today = $today ?: Carbon::today(config('app.timezone'));
        $end = $today->copy()->addDays(7);

        return $query->whereIn('status', ['sent', 'partial'])
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $end);
    }

    public function billingDetails(): array
    {
        $this->loadMissing('user');
        $user = $this->user;

        $name = $this->billing_name_override
            ?: ($user?->billing_name ?: $user?->name);
        $email = $this->billing_email_override
            ?: ($user?->billing_email ?: $user?->email);
        $phone = $this->billing_phone_override
            ?: ($user?->billing_phone);
        $address = $this->billing_address_override
            ?: ($user?->billing_address);
        $footer = $this->invoice_footer_note_override
            ?: ($user?->invoice_footer_note);
        $heading = $this->branding_heading_override
            ?: ($user?->branding_heading);

        $addressLines = $address
            ? preg_split("/\r\n|\n|\r/", trim($address)) ?: []
            : [];

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'address_lines' => array_filter($addressLines, fn ($line) => trim($line) !== ''),
            'footer_note' => $footer,
            'heading' => $heading,
        ];
    }


    public static function nextNumberForUser(int $userId): string
    {
        // Start from the highest existing INV-#### for this user
        $last = static::where('user_id', $userId)
            ->where('number', 'like', 'INV-%')
            ->orderByDesc('id')
            ->value('number');

        $n = 0;
        if ($last && preg_match('/^INV-(\d{4,})$/', $last, $m)) {
            $n = (int) $m[1];
        }

        // Find the next free number (handles gaps/soft-deletes safely)
        do {
            $n++;
            $candidate = 'INV-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        } while (static::where('user_id', $userId)->where('number', $candidate)->exists());

        return $candidate;
    }

    public function getPaidSatsAttribute(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments
                ->filter(fn (InvoicePayment $payment) => $this->paymentIsConfirmed($payment))
                ->sum('sats_received');
        }

        return (int) $this->payments()
            ->where(function (Builder $query) {
                $query->whereNotNull('confirmed_at')
                    ->orWhere('is_adjustment', true);
            })
            ->sum('sats_received');
    }

    public function getOutstandingSatsAttribute(): ?int
    {
        $expected = $this->expectedPaymentSats();
        if ($expected === null) {
            return null;
        }

        return max($expected - $this->paid_sats, 0);
    }

    public function hasSignificantOverpayment(): bool
    {
        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        if ($expectedUsd === null || $expectedUsd <= 0) {
            return false;
        }

        $confirmedUsd = $this->sumPaymentsUsd(true);
        if ($confirmedUsd <= $expectedUsd) {
            return false;
        }

        $surplusUsd = $confirmedUsd - $expectedUsd;
        $percent = ($surplusUsd / $expectedUsd) * 100;

        return $surplusUsd >= self::OVERPAY_USD_TOLERANCE || $percent >= self::OVERPAY_PERCENT_TOLERANCE;
    }

    public function hasSignificantUnderpayment(): bool
    {
        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        if ($expectedUsd === null || $expectedUsd <= 0) {
            return false;
        }

        $confirmedUsd = $this->sumPaymentsUsd(true);
        if ($confirmedUsd + self::UNDERPAY_USD_TOLERANCE >= $expectedUsd) {
            return false;
        }

        $deficitUsd = max($expectedUsd - $confirmedUsd, 0);
        $percent = ($deficitUsd / $expectedUsd) * 100;

        return $deficitUsd >= self::UNDERPAY_USD_TOLERANCE || $percent >= self::UNDERPAY_PERCENT_TOLERANCE;
    }

    public function overpaymentPercent(): ?float
    {
        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        if ($expectedUsd === null || $expectedUsd <= 0) {
            return null;
        }

        $confirmedUsd = $this->sumPaymentsUsd(true);
        if ($confirmedUsd <= $expectedUsd) {
            return null;
        }

        $surplusUsd = $confirmedUsd - $expectedUsd;
        return ($surplusUsd / $expectedUsd) * 100;
    }

    public function underpaymentPercent(): ?float
    {
        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        if ($expectedUsd === null || $expectedUsd <= 0) {
            return null;
        }

        $confirmedUsd = $this->sumPaymentsUsd(true);
        if ($confirmedUsd + self::UNDERPAY_USD_TOLERANCE >= $expectedUsd) {
            return null;
        }

        $deficitUsd = max($expectedUsd - $confirmedUsd, 0);
        return ($deficitUsd / $expectedUsd) * 100;
    }

    public function requiresClientOverpayAlert(): bool
    {
        $percent = $this->overpaymentPercent();
        return $percent !== null && $percent >= self::CLIENT_ALERT_PERCENT;
    }

    public function requiresClientUnderpayAlert(): bool
    {
        $percent = $this->underpaymentPercent();
        return $percent !== null && $percent >= self::CLIENT_ALERT_PERCENT;
    }

    public function refreshPaymentState(?\Illuminate\Support\Carbon $reference = null): void
    {
        $this->loadMissing('payments');
        $originalStatus = $this->status;
        $becamePaid = false;
        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;

        $paidSats = $this->sumPaymentSats(true);
        $this->payment_amount_sat = $paidSats;

        $confirmedUsd = $this->sumPaymentsUsd(true);
        $hasUnconfirmed = $this->hasUnconfirmedPayments();

        if ($expectedUsd !== null && $expectedUsd > 0) {
            if ($confirmedUsd >= $expectedUsd) {
                if ($this->status !== 'paid') {
                    $this->status = 'paid';
                    $becamePaid = true;
                }

                if (!$this->paid_at) {
                    $firstConfirmed = $this->payments
                        ->filter(fn (InvoicePayment $p) => $this->paymentIsConfirmed($p))
                        ->min('confirmed_at');
                    $this->paid_at = $reference ?? $firstConfirmed ?? now();
                }
            } elseif (!in_array($this->status, ['draft','void'], true)) {
                $this->status = $confirmedUsd > 0 ? 'partial' : ($hasUnconfirmed ? 'pending' : $this->status);
            }
        }

        $this->save();

        if ($becamePaid && $this->status === 'paid') {
            event(new \App\Events\InvoicePaid($this->fresh(['client','user','deliveries'])));
        }
    }

    /**
     * Build a BIP21 URI using an optional override amount.
     */
    public function bitcoinUriForAmount(?float $amountBtc, bool $allowFallback = true): ?string
    {
        if (!$this->payment_address) {
            return null;
        }

        $params = [];

        $amountToUse = $amountBtc;
        if ($amountToUse === null && $allowFallback && !empty($this->amount_btc)) {
            $amountToUse = (float) $this->amount_btc;
        }

        $formattedAmount = $this->formatBitcoinAmount($amountToUse);
        if ($formattedAmount !== null) {
            $params['amount'] = $formattedAmount;
        }

        // Optional label/message (kept short)
        if (!empty($this->number)) {
            $params['label'] = 'Invoice ' . $this->number;
        }
        if (!empty($this->description)) {
            $msg = mb_strimwidth((string)$this->description, 0, 140, '…');
            if ($msg !== '') {
                $params['message'] = $msg;
            }
        }

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return 'bitcoin:' . $this->payment_address . ($query ? ('?' . $query) : '');
    }

    public function getBitcoinUriAttribute(): ?string
    {
        return $this->bitcoinUriForAmount(null);
    }

    public function formatBitcoinAmount(?float $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        $amount = (float) $amount;
        if ($amount <= 0) {
            return null;
        }

        $formatted = number_format($amount, 8, '.', '');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');

        return ($trimmed !== '' && $trimmed !== '0') ? $trimmed : null;
    }

    public function getPaymentAmountBtcAttribute(): ?float
    {
        if ($this->payment_amount_sat === null) {
            return null;
        }

        if ($this->payment_amount_sat <= 0) {
            return null;
        }

        return round($this->payment_amount_sat / self::SATS_PER_BTC, 8);
    }

    public function getPaymentAmountFormattedAttribute(): ?string
    {
        $amount = $this->payment_amount_btc;
        return $amount === null ? null : $this->formatBitcoinAmount($amount);
    }

    public function expectedPaymentSats(?float $rateUsd = null): ?int
    {
        $amountUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        $effectiveRate = $rateUsd ?? ($this->btc_rate !== null ? (float) $this->btc_rate : null);

        if ($amountUsd !== null && $amountUsd > 0 && $effectiveRate && $effectiveRate > 0) {
            $btc = $amountUsd / $effectiveRate;
            return (int) round($btc * self::SATS_PER_BTC);
        }

        $btcSnapshot = $this->amount_btc !== null ? (float) $this->amount_btc : null;
        if ($btcSnapshot !== null && $btcSnapshot > 0) {
            return (int) round($btcSnapshot * self::SATS_PER_BTC);
        }

        return null;
    }

    public function paymentSummary(?array $rate = null, ?float $computedBtc = null): array
    {
        $this->loadMissing('payments');

        $expectedUsd = $this->amount_usd !== null ? (float) $this->amount_usd : null;
        $rateUsd = isset($rate['rate_usd']) ? (float) $rate['rate_usd'] : null;

        $expectedBtcFloat = $computedBtc ?? ($rateUsd && $expectedUsd
            ? round($expectedUsd / $rateUsd, 8)
            : ($this->amount_btc !== null ? (float) $this->amount_btc : null));

        $expectedSats = $expectedBtcFloat !== null
            ? (int) round($expectedBtcFloat * self::SATS_PER_BTC)
            : $this->expectedPaymentSats();

        $receivedUsd = $this->sumPaymentsUsd();
        $confirmedUsd = $this->sumPaymentsUsd(true);
        $receivedSats = $this->sumPaymentSats();
        $confirmedSats = $this->sumPaymentSats(true);

        $outstandingUsd = $expectedUsd !== null
            ? max($expectedUsd - $confirmedUsd, 0.0)
            : null;

        $effectiveRate = $rateUsd ?? ($this->btc_rate ? (float) $this->btc_rate : null);
        $outstandingBtcFloat = null;
        if ($outstandingUsd !== null && $outstandingUsd > 0 && $effectiveRate && $effectiveRate > 0) {
            $outstandingBtcFloat = round($outstandingUsd / $effectiveRate, 8);
        }

        $outstandingSats = $expectedSats !== null
            ? max($expectedSats - $confirmedSats, 0)
            : null;

        $lastDetected = $this->payments->max('detected_at');
        $lastConfirmed = $this->payments
            ->filter(fn (InvoicePayment $payment) => $this->paymentIsConfirmed($payment))
            ->max('confirmed_at');

        return [
            'expected_usd' => $expectedUsd,
            'expected_btc_formatted' => $this->formatBitcoinAmount($expectedBtcFloat),
            'expected_sats' => $expectedSats,
            'received_usd' => round($receivedUsd, 2),
            'received_sats' => $receivedSats,
            'confirmed_usd' => round($confirmedUsd, 2),
            'confirmed_sats' => $confirmedSats,
            'outstanding_usd' => $outstandingUsd !== null ? round($outstandingUsd, 2) : null,
            'outstanding_btc_formatted' => $this->formatBitcoinAmount($outstandingBtcFloat),
            'outstanding_btc_float' => $outstandingBtcFloat,
            'outstanding_sats' => $outstandingSats,
            'last_payment_detected_at' => $lastDetected,
            'last_payment_confirmed_at' => $lastConfirmed,
            'small_balance_threshold_usd' => $expectedUsd !== null ? $this->smallBalanceResolutionThresholdUsd($expectedUsd) : null,
        ];
    }

// Generate a unique token
    public static function generatePublicToken(): string
    {
        do {
            $token = Str::random(48); // ~288 bits
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

// Enable/disable public share (optionally with expiry)
    public function enablePublicShare(?\Carbon\Carbon $expires = null): self
    {
        if (!$this->public_token) {
            $this->public_token = static::generatePublicToken();
        }
        $this->public_enabled = true;
        $this->public_expires_at = $expires;
        return tap($this)->save();
    }

    public function disablePublicShare(): self
    {
        $this->public_enabled = false;
        $this->public_expires_at = null;
        return tap($this)->save();
    }

// Absolute public URL accessor
    public function getPublicUrlAttribute(): ?string
    {
        if (!$this->public_enabled || !$this->public_token) return null;
        $path = route('invoices.public-print', ['token' => $this->public_token], false);
        $base = rtrim(config('app.public_url', config('app.url')), '/');
        return $base . $path;
    }

    public function shouldWarnAboutPartialPayments(): bool
    {
        if (in_array($this->status, ['paid','void'])) {
            return false;
        }

        $outstanding = $this->outstanding_sats;
        if ($outstanding === null || $outstanding <= 0) {
            return false;
        }

        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        return $payments->count() >= 2;
    }

    public function sumPaymentsUsd(bool $confirmedOnly = false): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        if ($confirmedOnly) {
            $payments = $payments->filter(fn (InvoicePayment $payment) => $this->paymentIsConfirmed($payment));
        }

        return $payments->sum(fn (InvoicePayment $payment) => $this->paymentFiatValue($payment));
    }

    public function sumPaymentSats(bool $confirmedOnly = false): int
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments
            : $this->payments()->get();

        if ($confirmedOnly) {
            $payments = $payments->filter(fn (InvoicePayment $payment) => $this->paymentIsConfirmed($payment));
        }

        return (int) $payments->sum('sats_received');
    }

    public function hasUnconfirmedPayments(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains(fn (InvoicePayment $payment) => !$this->paymentIsConfirmed($payment));
        }

        return $this->payments()
            ->whereNull('confirmed_at')
            ->where('is_adjustment', false)
            ->exists();
    }

    private function paymentFiatValue(InvoicePayment $payment): float
    {
        if ($payment->fiat_amount !== null) {
            return (float) $payment->fiat_amount;
        }

        if ($payment->usd_rate !== null) {
            return round(($payment->sats_received / self::SATS_PER_BTC) * (float) $payment->usd_rate, 2);
        }

        if ($this->btc_rate) {
            return round(($payment->sats_received / self::SATS_PER_BTC) * (float) $this->btc_rate, 2);
        }

        return 0.0;
    }

    private function paymentIsConfirmed(InvoicePayment $payment): bool
    {
        return $payment->is_adjustment || $payment->confirmed_at !== null;
    }

    public function smallBalanceResolutionThresholdUsd(float $expectedUsd): float
    {
        if ($expectedUsd <= 0) {
            return 0.0;
        }

        $percentPortion = $expectedUsd * self::SMALL_BALANCE_RESOLUTION_PERCENT;
        $ceiling = min($percentPortion, self::SMALL_BALANCE_RESOLUTION_CAP_USD);

        return max(self::SMALL_BALANCE_RESOLUTION_FLOOR_USD, round($ceiling, 2));
    }
}
