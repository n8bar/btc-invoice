<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class InvoicePayment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (InvoicePayment $payment): void {
            if ($payment->accounting_invoice_id === null && $payment->ignored_at === null) {
                $payment->accounting_invoice_id = $payment->invoice_id;
            }
        });
    }

    protected $fillable = [
        'invoice_id',
        'accounting_invoice_id',
        'txid',
        'vout_index',
        'sats_received',
        'detected_at',
        'confirmed_at',
        'block_height',
        'usd_rate',
        'fiat_amount',
        'note',
        'ignored_at',
        'ignored_by_user_id',
        'ignore_reason',
        'reattributed_at',
        'reattributed_by_user_id',
        'reattribute_reason',
        'meta',
        'is_adjustment',
    ];

    protected $casts = [
        'sats_received' => 'int',
        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'ignored_at' => 'datetime',
        'reattributed_at' => 'datetime',
        'block_height' => 'int',
        'usd_rate' => 'decimal:2',
        'fiat_amount' => 'decimal:2',
        'meta' => 'array',
        'is_adjustment' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->invoice();
    }

    public function accountingInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'accounting_invoice_id');
    }

    public function ignoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by_user_id');
    }

    public function reattributedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reattributed_by_user_id');
    }

    public function scopeForUserInvoices(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->whereHas('accountingInvoice', function (Builder $invoiceQuery) use ($userId) {
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('invoice_payments.ignored_at');
    }

    public function scopeIgnored(Builder $query): Builder
    {
        return $query->whereNotNull('invoice_payments.ignored_at');
    }

    public function isIgnored(): bool
    {
        return $this->ignored_at !== null;
    }

    public function activeAccountingInvoiceId(): ?int
    {
        if ($this->isIgnored()) {
            return null;
        }

        return $this->accounting_invoice_id ?? $this->invoice_id;
    }

    public function isReattributed(): bool
    {
        $accountingInvoiceId = $this->activeAccountingInvoiceId();

        return $accountingInvoiceId !== null
            && $accountingInvoiceId !== $this->invoice_id;
    }

    public function belongsToSourceInvoice(Invoice|int $invoice): bool
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : $invoice;

        return $this->invoice_id === $invoiceId;
    }

    public function countsOnInvoice(Invoice|int $invoice): bool
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : $invoice;

        return $this->activeAccountingInvoiceId() === $invoiceId;
    }

    public function isReattributedOutFrom(Invoice|int $invoice): bool
    {
        return $this->belongsToSourceInvoice($invoice)
            && $this->isReattributed();
    }

    public function isReattributedInto(Invoice|int $invoice): bool
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->id : $invoice;

        return $this->countsOnInvoice($invoiceId)
            && !$this->belongsToSourceInvoice($invoiceId);
    }
}
