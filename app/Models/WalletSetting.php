<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletSetting extends Model
{
    protected $fillable = [
        'user_id',
        'network',
        'bip84_xpub',
        'next_derivation_index',
        'onboarded_at',
    ];

    protected $casts = [
        'onboarded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
