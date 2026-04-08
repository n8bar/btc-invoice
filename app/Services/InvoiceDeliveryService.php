<?php

namespace App\Services;

use App\Jobs\DeliverInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvoiceDeliveryService
{
    public function queue(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $cc = null,
        ?string $message = null,
        ?string $contextKey = null,
        ?array $meta = null,
    ): InvoiceDelivery {
        $intentKey = $this->intentKey($invoice, $type, $recipient, $contextKey);
        $lock = Cache::lock($this->intentLockName($intentKey), 10);

        if (! $lock->get()) {
            return $this->createDelivery(
                $invoice,
                $type,
                $recipient,
                $cc,
                $message,
                'A matching delivery intent is already being processed.',
                $intentKey,
                $contextKey,
                $meta,
            );
        }

        try {
            return $this->createDelivery(
                $invoice,
                $type,
                $recipient,
                $cc,
                $message,
                $this->skipReason($invoice, $type, $recipient, $contextKey),
                $intentKey,
                $contextKey,
                $meta,
            );
        } finally {
            $lock->release();
        }
    }

    public function skip(
        Invoice $invoice,
        string $type,
        string $recipient,
        string $reason,
        ?string $cc = null,
        ?string $message = null,
        ?string $contextKey = null,
        ?array $meta = null,
    ): InvoiceDelivery {
        $intentKey = $this->intentKey($invoice, $type, $recipient, $contextKey);
        $lock = Cache::lock($this->intentLockName($intentKey), 10);

        if (! $lock->get()) {
            return $this->createDelivery(
                $invoice,
                $type,
                $recipient,
                $cc,
                $message,
                'A matching delivery intent is already being processed.',
                $intentKey,
                $contextKey,
                $meta,
            );
        }

        try {
            return $this->createDelivery(
                $invoice,
                $type,
                $recipient,
                $cc,
                $message,
                $reason,
                $intentKey,
                $contextKey,
                $meta,
            );
        } finally {
            $lock->release();
        }
    }

    public function intentKey(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): string
    {
        return $this->buildIntentKey($invoice->id, $invoice->user_id, $type, $recipient, $contextKey);
    }

    public function intentKeyForDelivery(InvoiceDelivery $delivery): string
    {
        return $this->buildIntentKey(
            $delivery->invoice_id,
            $delivery->user_id,
            $delivery->type,
            $delivery->recipient,
            $delivery->context_key
        );
    }

    public function queueResend(Invoice $invoice, string $type, string $recipient): ?InvoiceDelivery
    {
        $cooldownMinutes = $this->manualSendCooldownMinutes();

        if ($cooldownMinutes > 0) {
            $cutoff = now()->subMinutes($cooldownMinutes);

            $recentExists = $invoice->deliveries()
                ->where('type', $type)
                ->whereRaw('LOWER(TRIM(recipient)) = ?', [$this->normalizeRecipient($recipient)])
                ->whereIn('status', ['queued', 'sending', 'sent'])
                ->where(function ($query) use ($cutoff) {
                    $query->where('dispatched_at', '>=', $cutoff)
                        ->orWhere('sent_at', '>=', $cutoff)
                        ->orWhere('created_at', '>=', $cutoff);
                })
                ->exists();

            if ($recentExists) {
                return null;
            }
        }

        $contextKey = 'resend_' . Str::uuid();

        return $this->queue($invoice, $type, $recipient, contextKey: $contextKey);
    }

    public function deliveryExists(
        Invoice $invoice,
        string $type,
        string $recipient,
        string $contextKey
    ): bool {
        return $this->matchingDeliveries($invoice, $type, $recipient, $contextKey)->exists();
    }

    public function outboundEnabled(): bool
    {
        return (bool) config('mail.safety.outbound_enabled', true);
    }

    public function alertCooldownMinutes(): int
    {
        return max((int) config('mail.safety.alert_cooldown_minutes', 1440), 0);
    }

    public function manualSendCooldownMinutes(): int
    {
        return max((int) config('mail.safety.manual_send_cooldown_minutes', 60), 0);
    }

    private function createDelivery(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $cc,
        ?string $message,
        ?string $skipReason,
        string $intentKey,
        ?string $contextKey,
        ?array $meta,
    ): InvoiceDelivery {
        $normalizedContextKey = $this->normalizeContextKey($contextKey);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => $type,
            'context_key' => $normalizedContextKey,
            'status' => $skipReason ? 'skipped' : 'queued',
            'recipient' => $recipient,
            'cc' => $cc,
            'message' => $message,
            'meta' => $meta,
            'dispatched_at' => now(),
            'error_message' => $skipReason,
        ]);

        if ($skipReason) {
            Log::info('invoice_delivery.skipped', [
                'invoice_id' => $invoice->id,
                'delivery_id' => $delivery->id,
                'type' => $type,
                'recipient' => $recipient,
                'context_key' => $normalizedContextKey,
                'intent_key' => $intentKey,
                'reason' => $skipReason,
            ]);

            return $delivery;
        }

        Log::info('invoice_delivery.queued', [
            'invoice_id' => $invoice->id,
            'delivery_id' => $delivery->id,
            'type' => $type,
            'recipient' => $recipient,
            'context_key' => $normalizedContextKey,
            'intent_key' => $intentKey,
        ]);

        DeliverInvoiceMail::dispatch($delivery);

        return $delivery;
    }

    private function skipReason(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): ?string
    {
        if (! $this->outboundEnabled()) {
            return 'Outbound mail is temporarily disabled.';
        }

        if ($this->hasQueuedDelivery($invoice, $type, $recipient, $contextKey)) {
            return 'A matching delivery is already queued.';
        }

        if ($contextKey !== null && $this->hasFailedDelivery($invoice, $type, $recipient, $contextKey)) {
            return 'A matching delivery has already been attempted for this trigger.';
        }

        if ($this->preventsRepeatAfterSend($type)
            && $this->hasSentDelivery($invoice, $type, $recipient, $contextKey)) {
            return $this->sentDuplicateReason($type);
        }

        if ($type === 'send'
            && $this->manualSendCooldownMinutes() > 0
            && $this->hasRecentQueuedOrSentDelivery(
                $invoice,
                $type,
                $recipient,
                $this->manualSendCooldownMinutes(),
                $contextKey
            )) {
            return 'Invoice email skipped because the same notice was already queued or sent recently.';
        }

        return null;
    }

    private function hasQueuedDelivery(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): bool
    {
        return $this->matchingDeliveries($invoice, $type, $recipient, $contextKey)
            ->where('status', 'queued')
            ->exists();
    }

    private function hasFailedDelivery(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): bool
    {
        return $this->matchingDeliveries($invoice, $type, $recipient, $contextKey)
            ->where('status', 'failed')
            ->exists();
    }

    private function hasSentDelivery(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): bool
    {
        return $this->matchingDeliveries($invoice, $type, $recipient, $contextKey)
            ->where('status', 'sent')
            ->exists();
    }

    private function hasRecentQueuedOrSentDelivery(
        Invoice $invoice,
        string $type,
        string $recipient,
        int $minutes,
        ?string $contextKey = null
    ): bool
    {
        $cutoff = now()->subMinutes($minutes);

        return $this->matchingDeliveries($invoice, $type, $recipient, $contextKey)
            ->whereIn('status', ['queued', 'sent'])
            ->where(function ($query) use ($cutoff) {
                $query->where('dispatched_at', '>=', $cutoff)
                    ->orWhere('sent_at', '>=', $cutoff)
                    ->orWhere('created_at', '>=', $cutoff);
            })
            ->exists();
    }

    private function matchingDeliveries(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $contextKey = null
    )
    {
        $query = $invoice->deliveries()
            ->where('type', $type)
            ->whereRaw('LOWER(TRIM(recipient)) = ?', [$this->normalizeRecipient($recipient)]);

        $normalizedContextKey = $this->normalizeContextKey($contextKey);

        if ($normalizedContextKey === null) {
            return $query->whereNull('context_key');
        }

        return $query->whereRaw('LOWER(TRIM(context_key)) = ?', [$normalizedContextKey]);
    }

    private function intentLockName(string $intentKey): string
    {
        return 'invoice-delivery-intent:' . $intentKey;
    }

    private function buildIntentKey(
        int $invoiceId,
        int $userId,
        string $type,
        string $recipient,
        ?string $contextKey = null
    ): string
    {
        return hash('sha256', implode('|', [
            $invoiceId,
            $userId,
            $type,
            $this->normalizeRecipient($recipient),
            $this->normalizeContextKey($contextKey) ?? '',
        ]));
    }

    private function normalizeRecipient(string $recipient): string
    {
        return strtolower(trim($recipient));
    }

    private function normalizeContextKey(?string $contextKey): ?string
    {
        if ($contextKey === null) {
            return null;
        }

        $normalized = strtolower(trim($contextKey));

        return $normalized === '' ? null : $normalized;
    }

    private function preventsRepeatAfterSend(string $type): bool
    {
        return in_array($type, [
            'receipt',
            'issuer_paid_notice',
            'payment_acknowledgment_client',
            'payment_acknowledgment_issuer',
            'past_due_issuer',
            'past_due_client',
        ], true);
    }

    private function sentDuplicateReason(string $type): string
    {
        return match ($type) {
            'receipt' => 'A receipt has already been queued or sent for this invoice.',
            'issuer_paid_notice' => 'An issuer paid notice has already been queued or sent for this invoice.',
            'payment_acknowledgment_client',
            'payment_acknowledgment_issuer' => 'A payment acknowledgment has already been queued or sent for this detected payment.',
            'past_due_issuer',
            'past_due_client' => 'This past-due notice has already been queued or sent.',
            default => 'A matching delivery has already been queued or sent.',
        };
    }
}
