<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;

class WalletSetting extends Model
{
    protected $fillable = [
        'user_id',
        'network',
        'bip84_xpub',
        'onboarded_at',
        'unsupported_configuration_active',
        'unsupported_configuration_source',
        'unsupported_configuration_reason',
        'unsupported_configuration_details',
        'unsupported_configuration_flagged_at',
    ];

    protected $casts = [
        'onboarded_at' => 'datetime',
        'unsupported_configuration_active' => 'boolean',
        'unsupported_configuration_source' => 'string',
        'unsupported_configuration_reason' => 'string',
        'unsupported_configuration_details' => 'string',
        'unsupported_configuration_flagged_at' => 'datetime',
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

    public function markUnsupportedConfiguration(string $source, string $reason, ?string $details = null, ?Carbon $flaggedAt = null): void
    {
        $this->forceFill([
            'unsupported_configuration_active' => true,
            'unsupported_configuration_source' => $source,
            'unsupported_configuration_reason' => $reason,
            'unsupported_configuration_details' => $details,
            'unsupported_configuration_flagged_at' => $flaggedAt ?? now(),
        ])->save();
    }

    public function clearUnsupportedConfiguration(): void
    {
        $this->forceFill([
            'unsupported_configuration_active' => false,
            'unsupported_configuration_source' => null,
            'unsupported_configuration_reason' => null,
            'unsupported_configuration_details' => null,
            'unsupported_configuration_flagged_at' => null,
        ])->save();
    }

    public function invoiceUnsupportedConfigurationSnapshot(): array
    {
        if (! $this->unsupported_configuration_active) {
            return [
                'unsupported_configuration_flagged' => false,
                'unsupported_configuration_source' => null,
                'unsupported_configuration_reason' => null,
                'unsupported_configuration_details' => null,
                'unsupported_configuration_flagged_at' => null,
            ];
        }

        return [
            'unsupported_configuration_flagged' => true,
            'unsupported_configuration_source' => $this->unsupported_configuration_source,
            'unsupported_configuration_reason' => $this->unsupported_configuration_reason,
            'unsupported_configuration_details' => $this->unsupported_configuration_details,
            'unsupported_configuration_flagged_at' => $this->unsupported_configuration_flagged_at,
        ];
    }
}
