<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;

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
