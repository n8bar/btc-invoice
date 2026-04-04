<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InvoiceDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'type',
        'context_key',
        'status',
        'recipient',
        'cc',
        'message',
        'meta',
        'dispatched_at',
        'sent_at',
        'error_code',
        'error_message',
        'provider_message_id',
    ];

    protected $casts = [
        'meta' => 'array',
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

    public function typeLabel(): string
    {
        return match ($this->type) {
            'send' => 'Invoice email',
            'payment_acknowledgment_client' => 'Payment acknowledgment (client)',
            'payment_acknowledgment_issuer' => 'Payment acknowledgment (issuer)',
            'receipt' => 'Receipt (client)',
            'issuer_paid_notice' => 'Paid notice (issuer)',
            'past_due_client' => 'Past-due reminder (client)',
            'past_due_issuer' => 'Past-due reminder (issuer)',
            'client_overpay_alert' => 'Overpayment alert (client)',
            'issuer_overpay_alert' => 'Overpayment alert (issuer)',
            'client_underpay_alert' => 'Underpayment alert (client)',
            'issuer_underpay_alert' => 'Underpayment alert (issuer)',
            'client_partial_warning' => 'Partial payment warning (client)',
            'issuer_partial_warning' => 'Partial payment warning (issuer)',
            default => Str::of($this->type)->replace('_', ' ')->headline()->toString(),
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued' => 'Queued',
            'sending' => 'Sending',
            'sent' => 'Sent',
            'skipped' => 'Skipped',
            'failed' => 'Failed',
            default => Str::of($this->status)->replace('_', ' ')->headline()->toString(),
        };
    }
}
