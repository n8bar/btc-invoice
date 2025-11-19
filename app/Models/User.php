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
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'invoice_footer_note',
        'branding_heading',
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
            'billing_name' => 'string',
            'billing_email' => 'string',
            'billing_phone' => 'string',
            'branding_heading' => 'string',
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
}
