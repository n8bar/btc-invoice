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
        'amount_usd','btc_rate','amount_btc','btc_address',
        'status','txid','invoice_date','due_date','paid_at',
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
    ];

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

    public function getBitcoinUriAttribute(): ?string
    {
        if (!$this->btc_address) {
            return null;
        }

        $params = [];

        // amount must be in BTC with up to 8 decimals
        if (!empty($this->amount_btc) && (float)$this->amount_btc > 0) {
            $amt = number_format((float)$this->amount_btc, 8, '.', '');
            $amt = rtrim(rtrim($amt, '0'), '.'); // trim trailing zeros/dot
            if ($amt !== '' && $amt !== '0') {
                $params['amount'] = $amt;
            }
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
        return 'bitcoin:' . $this->btc_address . ($query ? ('?' . $query) : '');
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
