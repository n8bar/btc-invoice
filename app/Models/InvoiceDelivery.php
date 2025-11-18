<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'type',
        'status',
        'recipient',
        'cc',
        'message',
        'dispatched_at',
        'sent_at',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
