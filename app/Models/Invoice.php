<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'last_overpayment_alert_at','last_underpayment_alert_at',
        'last_past_due_owner_alert_at','last_past_due_client_alert_at',
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
    ];
    public const SATS_PER_BTC = 100_000_000;
    public const PAYMENT_SAT_TOLERANCE = 100;
    public const OVERPAY_USD_TOLERANCE = 10.0;
    public const OVERPAY_PERCENT_TOLERANCE = 1.0;
    public const UNDERPAY_USD_TOLERANCE = 10.0;
    public const UNDERPAY_PERCENT_TOLERANCE = 1.0;
    public const CLIENT_ALERT_PERCENT = 15.0;

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function payments(): HasMany { return $this->hasMany(InvoicePayment::class); }
    public function deliveries(): HasMany { return $this->hasMany(InvoiceDelivery::class); }


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
            return (int) $this->payments->sum('sats_received');
        }

        return (int) $this->payments()->sum('sats_received');
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
        $expected = $this->expectedPaymentSats();
        if ($expected === null || $expected <= 0) {
            return false;
        }

        $paid = $this->paid_sats;
        if ($paid <= $expected) {
            return false;
        }

        $surplus = $paid - $expected;
        $usdRate = $this->btc_rate ?: null;
        $surplusUsd = $usdRate ? ($surplus / self::SATS_PER_BTC) * (float) $usdRate : 0;
        $percent = ($surplus / $expected) * 100;

        return $surplusUsd >= self::OVERPAY_USD_TOLERANCE || $percent >= self::OVERPAY_PERCENT_TOLERANCE;
    }

    public function hasSignificantUnderpayment(): bool
    {
        $expected = $this->expectedPaymentSats();
        if ($expected === null || $expected <= 0) {
            return false;
        }

        $paid = $this->paid_sats;
        if ($paid + self::PAYMENT_SAT_TOLERANCE >= $expected) {
            return false;
        }

        $deficit = max($expected - $paid, 0);
        $usdRate = $this->btc_rate ?: null;
        $deficitUsd = $usdRate ? ($deficit / self::SATS_PER_BTC) * (float) $usdRate : 0;
        $percent = ($deficit / $expected) * 100;

        return $deficitUsd >= self::UNDERPAY_USD_TOLERANCE || $percent >= self::UNDERPAY_PERCENT_TOLERANCE;
    }

    public function overpaymentPercent(): ?float
    {
        $expected = $this->expectedPaymentSats();
        if ($expected === null || $expected <= 0) {
            return null;
        }

        $paid = $this->paid_sats;
        if ($paid <= $expected) {
            return null;
        }

        $surplus = $paid - $expected;
        return ($surplus / $expected) * 100;
    }

    public function underpaymentPercent(): ?float
    {
        $expected = $this->expectedPaymentSats();
        if ($expected === null || $expected <= 0) {
            return null;
        }

        $paid = $this->paid_sats;
        if ($paid + self::PAYMENT_SAT_TOLERANCE >= $expected) {
            return null;
        }

        $deficit = max($expected - $paid, 0);
        return ($deficit / $expected) * 100;
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
        $originalStatus = $this->status;
        $becamePaid = false;
        $paidSats = $this->payments()->sum('sats_received');
        $this->payment_amount_sat = $paidSats;

        $expected = $this->expectedPaymentSats();

        if ($expected !== null && $paidSats > 0) {
            if ($paidSats + self::PAYMENT_SAT_TOLERANCE >= $expected) {
                if ($this->status !== 'paid') {
                    $this->status = 'paid';
                    $becamePaid = true;
                }

                if (!$this->paid_at) {
                    $this->paid_at = $reference ?? now();
                }
            } elseif (!in_array($this->status, ['draft','void'], true)) {
                $this->status = 'partial';
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

    public function expectedPaymentSats(): ?int
    {
        if ($this->amount_btc === null) {
            return null;
        }

        $btc = (float) $this->amount_btc;
        if ($btc <= 0) {
            return null;
        }

        return (int) round($btc * self::SATS_PER_BTC);
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
}
