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

    public function typeLabel(): string
    {
        return match ($this->type) {
            'send' => 'Invoice email',
            'receipt' => 'Receipt (client)',
            'owner_paid_notice' => 'Paid notice (owner)',
            'past_due_client' => 'Past-due reminder (client)',
            'past_due_owner' => 'Past-due reminder (owner)',
            'client_overpay_alert' => 'Overpayment alert (client)',
            'owner_overpay_alert' => 'Overpayment alert (owner)',
            'client_underpay_alert' => 'Underpayment alert (client)',
            'owner_underpay_alert' => 'Underpayment alert (owner)',
            'client_partial_warning' => 'Partial payment warning (client)',
            'owner_partial_warning' => 'Partial payment warning (owner)',
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
