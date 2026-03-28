<?php

namespace App\Services;

use App\Jobs\DeliverInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Support\Facades\Log;

class InvoiceDeliveryService
{
    public function queue(
        Invoice $invoice,
        string $type,
        string $recipient,
        ?string $cc = null,
        ?string $message = null
    ): InvoiceDelivery {
        $skipReason = $this->skipReason($invoice, $type, $recipient);

        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => $type,
            'status' => $skipReason ? 'skipped' : 'queued',
            'recipient' => $recipient,
            'cc' => $cc,
            'message' => $message,
            'dispatched_at' => now(),
            'error_message' => $skipReason,
        ]);

        if ($skipReason) {
            Log::info('invoice_delivery.skipped', [
                'invoice_id' => $invoice->id,
                'delivery_id' => $delivery->id,
                'type' => $type,
                'recipient' => $recipient,
                'reason' => $skipReason,
            ]);

            return $delivery;
        }

        Log::info('invoice_delivery.queued', [
            'invoice_id' => $invoice->id,
            'delivery_id' => $delivery->id,
            'type' => $type,
            'recipient' => $recipient,
        ]);

        DeliverInvoiceMail::dispatch($delivery);

        return $delivery;
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

    private function skipReason(Invoice $invoice, string $type, string $recipient): ?string
    {
        if (! $this->outboundEnabled()) {
            return 'Outbound mail is temporarily disabled.';
        }

        if ($this->hasQueuedDelivery($invoice, $type, $recipient)) {
            return 'A matching delivery is already queued.';
        }

        if (in_array($type, ['receipt', 'owner_paid_notice'], true)
            && $this->hasSentDelivery($invoice, $type, $recipient)) {
            return $type === 'receipt'
                ? 'A receipt has already been queued or sent for this invoice.'
                : 'An owner paid notice has already been queued or sent for this invoice.';
        }

        if ($type === 'send'
            && $this->manualSendCooldownMinutes() > 0
            && $this->hasRecentQueuedOrSentDelivery($invoice, $type, $recipient, $this->manualSendCooldownMinutes())) {
            return 'Invoice email skipped because the same notice was already queued or sent recently.';
        }

        return null;
    }

    private function hasQueuedDelivery(Invoice $invoice, string $type, string $recipient): bool
    {
        return $this->matchingDeliveries($invoice, $type, $recipient)
            ->where('status', 'queued')
            ->exists();
    }

    private function hasSentDelivery(Invoice $invoice, string $type, string $recipient): bool
    {
        return $this->matchingDeliveries($invoice, $type, $recipient)
            ->where('status', 'sent')
            ->exists();
    }

    private function hasRecentQueuedOrSentDelivery(Invoice $invoice, string $type, string $recipient, int $minutes): bool
    {
        $cutoff = now()->subMinutes($minutes);

        return $this->matchingDeliveries($invoice, $type, $recipient)
            ->whereIn('status', ['queued', 'sent'])
            ->where(function ($query) use ($cutoff) {
                $query->where('dispatched_at', '>=', $cutoff)
                    ->orWhere('sent_at', '>=', $cutoff)
                    ->orWhere('created_at', '>=', $cutoff);
            })
            ->exists();
    }

    private function matchingDeliveries(Invoice $invoice, string $type, string $recipient)
    {
        return $invoice->deliveries()
            ->where('type', $type)
            ->where('recipient', $recipient);
    }
}
