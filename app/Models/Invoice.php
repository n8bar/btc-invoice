<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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


}
