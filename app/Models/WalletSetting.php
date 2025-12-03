<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

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

    /**
     * Store the xpub encrypted, but tolerate legacy plaintext rows.
     */
    protected function bip84Xpub(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null) {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    return $value; // legacy plaintext
                }
            },
            set: fn ($value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
