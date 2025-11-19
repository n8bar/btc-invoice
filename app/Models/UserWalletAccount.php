<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWalletAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'network',
        'bip84_xpub',
        'next_derivation_index',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
