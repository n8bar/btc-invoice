<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'txid',
        'vout_index',
        'sats_received',
        'detected_at',
        'confirmed_at',
        'block_height',
        'usd_rate',
        'fiat_amount',
        'note',
        'meta',
    ];

    protected $casts = [
        'sats_received' => 'int',
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'block_height' => 'int',
        'usd_rate' => 'decimal:2',
        'fiat_amount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
