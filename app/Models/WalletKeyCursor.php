<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletKeyCursor extends Model
{
    protected $fillable = [
        'user_id',
        'network',
        'key_fingerprint',
        'next_derivation_index',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $casts = [
        'next_derivation_index' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
