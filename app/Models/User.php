<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'getting_started_completed_at',
        'getting_started_dismissed',
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
            'getting_started_completed_at' => 'datetime',
            'getting_started_dismissed' => 'boolean',
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
}
