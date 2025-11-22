<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
        'is_adjustment',
    ];

    protected $casts = [
        'sats_received' => 'int',
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'block_height' => 'int',
        'usd_rate' => 'decimal:2',
        'fiat_amount' => 'decimal:2',
        'meta' => 'array',
        'is_adjustment' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeForUserInvoices(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->whereHas('invoice', function (Builder $invoiceQuery) use ($userId) {
            $invoiceQuery->ownedBy($userId);
        });
    }

    public function scopeRecentBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where(function (Builder $dateQuery) use ($from, $to) {
            $dateQuery->whereBetween('invoice_payments.detected_at', [$from, $to])
                ->orWhere(function (Builder $createdFallback) use ($from, $to) {
                    $createdFallback->whereNull('invoice_payments.detected_at')
                        ->whereBetween('invoice_payments.created_at', [$from, $to]);
                });
        });
    }
}
