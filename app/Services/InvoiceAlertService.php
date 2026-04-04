<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Support\Carbon;

class InvoiceAlertService
{
    public function __construct(private readonly InvoiceDeliveryService $deliveries)
    {
    }

    public function sendIssuerPaidNotice(Invoice $invoice): void
    {
        $issuer = $invoice->user;
        if (!$issuer || empty($issuer->email)) {
            return;
        }

        $this->deliveries->queue($invoice, 'issuer_paid_notice', $issuer->email);
    }

    public function sendDetectedPaymentAcknowledgments(Invoice $invoice, InvoicePayment $payment): void
    {
        if (! filled($payment->txid)) {
            return;
        }

        $invoice->loadMissing(['client', 'user']);

        $contextKey = $payment->txid;
        $meta = [
            'invoice_payment_id' => $payment->id,
            'txid' => $payment->txid,
            'sats_received' => $payment->sats_received,
            'detected_at' => $payment->detected_at?->toIso8601String(),
            'confirmed_at' => $payment->confirmed_at?->toIso8601String(),
        ];

        $client = $invoice->client;
        if ($client && filled($client->email)) {
            $this->deliveries->queue(
                $invoice,
                'payment_acknowledgment_client',
                $client->email,
                contextKey: $contextKey,
                meta: $meta,
            );
        }

        $issuer = $invoice->user;
        if ($issuer && filled($issuer->email)) {
            $this->deliveries->queue(
                $invoice,
                'payment_acknowledgment_issuer',
                $issuer->email,
                contextKey: $contextKey,
                meta: $meta,
            );
        }
    }

    public function checkPaymentThresholds(Invoice $invoice): void
    {
        if ($invoice->requiresClientOverpayAlert()) {
            $this->maybeSendOverpayAlert($invoice);
        }

        if ($invoice->requiresClientUnderpayAlert()) {
            $this->maybeSendUnderpayAlert($invoice);
        }
    }

    public function skipInvalidQueuedDeliveries(Invoice $invoice, string $reasonPrefix = 'Skipped after invoice state change.'): void
    {
        if ($invoice->status !== 'paid') {
            $this->skipQueuedDeliveries(
                $invoice,
                ['receipt', 'issuer_paid_notice'],
                "{$reasonPrefix} Invoice no longer paid."
            );
        }

        if (! $invoice->requiresClientUnderpayAlert()) {
            $this->skipQueuedDeliveries(
                $invoice,
                ['client_underpay_alert', 'issuer_underpay_alert'],
                "{$reasonPrefix} Underpayment alert no longer applies."
            );
        }

        if (! $invoice->requiresClientOverpayAlert()) {
            $this->skipQueuedDeliveries(
                $invoice,
                ['client_overpay_alert', 'issuer_overpay_alert'],
                "{$reasonPrefix} Overpayment alert no longer applies."
            );
        }

        if (! $invoice->shouldWarnAboutPartialPayments()) {
            $this->skipQueuedDeliveries(
                $invoice,
                ['client_partial_warning', 'issuer_partial_warning'],
                "{$reasonPrefix} Partial-payment warning no longer applies."
            );
        }
    }

    public function sendPastDueAlerts(Invoice $invoice): void
    {
        if ($invoice->status === 'paid' || $invoice->status === 'void') {
            return;
        }

        if (!$invoice->due_date || $invoice->due_date->isFuture()) {
            return;
        }

        $outstanding = $invoice->outstanding_sats;
        if ($outstanding !== null && $outstanding <= 0) {
            return;
        }

        $daysPastDue = (int) $invoice->due_date->diffInDays(now());

        // Sequence slot => minimum days past due required before sending.
        // One new slot fires per cron run; already-sent slots are blocked by the
        // delivery service's preventsRepeatAfterSend guard on the context key.
        $schedule = [1 => 1, 2 => 7, 3 => 14];

        $issuer = $invoice->user;
        $client = $invoice->client;

        foreach ($schedule as $seq => $minDays) {
            if ($daysPastDue < $minDays) {
                break;
            }

            $contextKey = "past_due_{$seq}";
            $newlyQueued = false;

            if ($issuer && filled($issuer->email)) {
                $delivery = $this->deliveries->queue($invoice, 'past_due_issuer', $issuer->email, contextKey: $contextKey);
                if ($delivery->status === 'queued') {
                    $newlyQueued = true;
                }
            }

            if ($client && filled($client->email)) {
                $delivery = $this->deliveries->queue($invoice, 'past_due_client', $client->email, contextKey: $contextKey);
                if ($delivery->status === 'queued') {
                    $newlyQueued = true;
                }
            }

            if ($newlyQueued) {
                break;
            }
        }
    }

    private function maybeSendOverpayAlert(Invoice $invoice): void
    {
        $client = $invoice->client;
        if (!$client || empty($client->email)) {
            return;
        }

        if (!$this->shouldSend($invoice->last_overpayment_alert_at)) {
            return;
        }

        $contextKey = $this->latestPaymentTxid($invoice);

        $delivery = $this->deliveries->queue($invoice, 'client_overpay_alert', $client->email, contextKey: $contextKey);
        if ($delivery->status !== 'queued') {
            return;
        }

        $invoice->last_overpayment_alert_at = now();

        $issuer = $invoice->user;
        if ($issuer && !empty($issuer->email)) {
            $this->deliveries->queue($invoice, 'issuer_overpay_alert', $issuer->email, contextKey: $contextKey);
        }

        $invoice->save();
    }

    private function maybeSendUnderpayAlert(Invoice $invoice): void
    {
        $client = $invoice->client;
        if (!$client || empty($client->email)) {
            return;
        }

        if (!$this->shouldSend($invoice->last_underpayment_alert_at)) {
            return;
        }

        $contextKey = $this->latestPaymentTxid($invoice);

        $delivery = $this->deliveries->queue($invoice, 'client_underpay_alert', $client->email, contextKey: $contextKey);
        if ($delivery->status !== 'queued') {
            return;
        }

        $invoice->last_underpayment_alert_at = now();

        $issuer = $invoice->user;
        if ($issuer && !empty($issuer->email)) {
            $this->deliveries->queue($invoice, 'issuer_underpay_alert', $issuer->email, contextKey: $contextKey);
        }

        $invoice->save();
    }

    private function latestPaymentTxid(Invoice $invoice): ?string
    {
        $txid = $invoice->payments()->orderBy('id', 'desc')->value('txid');
        return filled($txid) ? $txid : null;
    }

    private function shouldSend(?Carbon $lastSent): bool
    {
        if (!$lastSent) {
            return true;
        }

        return $lastSent->diffInMinutes(now()) >= $this->deliveries->alertCooldownMinutes();
    }

    private function skipQueuedDeliveries(Invoice $invoice, array $types, string $reason): void
    {
        $invoice->deliveries()
            ->whereIn('type', $types)
            ->where('status', 'queued')
            ->update([
                'status' => 'skipped',
                'error_message' => $reason,
            ]);
    }
}
