<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'show_invoice_ids',
        'auto_receipt_emails',
        'show_overpayment_gratuity_note',
        'show_qr_refresh_reminder',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'invoice_footer_note',
        'branding_heading',
        'invoice_default_description',
        'invoice_default_terms_days',
        'theme',
        'support_access_granted_at',
        'support_access_expires_at',
        'support_access_terms_version',
        'getting_started_completed_at',
        'getting_started_dismissed',
        'getting_started_replay_started_at',
        'getting_started_replay_wallet_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'show_invoice_ids' => 'boolean',
            'auto_receipt_emails' => 'boolean',
            'show_overpayment_gratuity_note' => 'boolean',
            'show_qr_refresh_reminder' => 'boolean',
            'billing_name' => 'string',
            'billing_email' => 'string',
            'billing_phone' => 'string',
            'branding_heading' => 'string',
            'invoice_default_terms_days' => 'integer',
            'theme' => 'string',
            'support_access_granted_at' => 'datetime',
            'support_access_expires_at' => 'datetime',
            'support_access_terms_version' => 'string',
            'getting_started_completed_at' => 'datetime',
            'getting_started_dismissed' => 'boolean',
            'getting_started_replay_started_at' => 'datetime',
            'getting_started_replay_wallet_verified_at' => 'datetime',
        ];
    }



    public function clients()
    {
        return $this->hasMany(\App\Models\Client::class);
    }

    public function invoices()
    {
        return $this->hasMany(\App\Models\Invoice::class);
    }
    public function walletSetting()
    {
        return $this->hasOne(WalletSetting::class);
    }

    public function walletAccounts()
    {
        return $this->hasMany(UserWalletAccount::class);
    }

    public function walletKeyCursors()
    {
        return $this->hasMany(WalletKeyCursor::class);
    }

    public function gettingStartedIsDone(): bool
    {
        return $this->getting_started_completed_at !== null;
    }

    public function gettingStartedWasDismissed(): bool
    {
        return $this->gettingStartedIsDone() && (bool) $this->getting_started_dismissed;
    }

    public function gettingStartedNeedsAutoShow(): bool
    {
        return $this->getting_started_completed_at === null;
    }

    public function gettingStartedReplayActive(): bool
    {
        return $this->getting_started_completed_at === null
            && $this->getting_started_replay_started_at !== null;
    }

    public function isSupportAgent(): bool
    {
        $email = Str::lower(trim((string) $this->email));

        return in_array($email, config('support.agent_emails', []), true);
    }

    public function hasActiveSupportAccessGrant(): bool
    {
        return $this->support_access_granted_at !== null
            && $this->support_access_expires_at !== null
            && $this->support_access_expires_at->isFuture();
    }

    public function grantSupportAccess(?Carbon $grantedAt = null): void
    {
        $grantedAt ??= now();

        $this->forceFill([
            'support_access_granted_at' => $grantedAt,
            'support_access_expires_at' => $grantedAt->copy()->addHours((int) config('support.grant_hours', 72)),
            'support_access_terms_version' => (string) config('support.terms_version', 'v1'),
        ])->save();
    }

    public function revokeSupportAccess(): void
    {
        $this->forceFill([
            'support_access_granted_at' => null,
            'support_access_expires_at' => null,
            'support_access_terms_version' => null,
        ])->save();
    }
}
