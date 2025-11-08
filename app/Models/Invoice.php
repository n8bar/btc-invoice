<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];
    public const SATS_PER_BTC = 100_000_000;

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }


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

    /**
     * Build a BIP21 URI using an optional override amount.
     */
    public function bitcoinUriForAmount(?float $amountBtc): ?string
    {
        if (!$this->payment_address) {
            return null;
        }

        $params = [];

        $amountToUse = $amountBtc;
        if ($amountToUse === null && !empty($this->amount_btc)) {
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
        return route('invoices.public-print', ['token' => $this->public_token], true);
    }
}
