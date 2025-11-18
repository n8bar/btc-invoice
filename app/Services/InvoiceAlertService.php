<?php

namespace App\Services;

use App\Jobs\DeliverInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceDelivery;
use Illuminate\Support\Carbon;

class InvoiceAlertService
{
    public const ALERT_COOLDOWN_MINUTES = 1440; // 24 hours

    public function sendOwnerPaidNotice(Invoice $invoice): void
    {
        $owner = $invoice->user;
        if (!$owner || empty($owner->email)) {
            return;
        }

        if ($invoice->deliveries()->where('type', 'owner_paid_notice')->exists()) {
            return;
        }

        $this->queueDelivery($invoice, 'owner_paid_notice', $owner->email);
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

        $owner = $invoice->user;
        if ($owner && $this->shouldSend($invoice->last_past_due_owner_alert_at)) {
            $this->queueDelivery($invoice, 'past_due_owner', $owner->email);
            $invoice->last_past_due_owner_alert_at = now();
        }

        $client = $invoice->client;
        if ($client && !empty($client->email) && $this->shouldSend($invoice->last_past_due_client_alert_at)) {
            $this->queueDelivery($invoice, 'past_due_client', $client->email);
            $invoice->last_past_due_client_alert_at = now();
        }

        $invoice->save();
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

        $this->queueDelivery($invoice, 'client_overpay_alert', $client->email);
        $invoice->last_overpayment_alert_at = now();

        $owner = $invoice->user;
        if ($owner && !empty($owner->email)) {
            $this->queueDelivery($invoice, 'owner_overpay_alert', $owner->email);
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

        $this->queueDelivery($invoice, 'client_underpay_alert', $client->email);
        $invoice->last_underpayment_alert_at = now();

        $owner = $invoice->user;
        if ($owner && !empty($owner->email)) {
            $this->queueDelivery($invoice, 'owner_underpay_alert', $owner->email);
        }

        $invoice->save();
    }

    private function queueDelivery(Invoice $invoice, string $type, string $recipient): void
    {
        $delivery = InvoiceDelivery::create([
            'invoice_id' => $invoice->id,
            'user_id' => $invoice->user_id,
            'type' => $type,
            'status' => 'queued',
            'recipient' => $recipient,
            'dispatched_at' => now(),
        ]);

        DeliverInvoiceMail::dispatch($delivery);
    }

    private function shouldSend(?Carbon $lastSent): bool
    {
        if (!$lastSent) {
            return true;
        }

        return $lastSent->diffInMinutes(now()) >= self::ALERT_COOLDOWN_MINUTES;
    }
}
