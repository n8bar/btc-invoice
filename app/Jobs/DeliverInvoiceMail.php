<?php

namespace App\Jobs;

use App\Mail\InvoiceOverpaymentClientMail;
use App\Mail\InvoiceOverpaymentOwnerMail;
use App\Mail\InvoicePastDueClientMail;
use App\Mail\InvoicePastDueOwnerMail;
use App\Mail\InvoicePaymentAcknowledgmentClientMail;
use App\Mail\InvoicePaymentAcknowledgmentOwnerMail;
use App\Mail\InvoicePaidReceiptMail;
use App\Mail\InvoiceReadyMail;
use App\Mail\InvoiceUnderpaymentClientMail;
use App\Mail\InvoiceUnderpaymentOwnerMail;
use App\Mail\InvoiceOwnerPaidNoticeMail;
use App\Models\Invoice;
use App\Mail\InvoicePartialWarningClientMail;
use App\Mail\InvoicePartialWarningOwnerMail;
use App\Models\InvoiceDelivery;
use App\Models\InvoicePayment;
use App\Services\InvoiceDeliveryService;
use App\Services\MailAlias;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliverInvoiceMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public InvoiceDelivery $delivery)
    {
    }

    public function handle(MailAlias $mailAlias, InvoiceDeliveryService $deliveries): void
    {
        $delivery = $this->delivery->fresh();
        if (!$delivery) {
            return;
        }

        $sendLock = Cache::lock($this->sendLockName($deliveries->intentKeyForDelivery($delivery)), 30);

        if (! $sendLock->get()) {
            Log::info('invoice_delivery.send_locked', [
                'delivery_id' => $delivery->id,
                'invoice_id' => $delivery->invoice_id,
                'type' => $delivery->type,
                'recipient' => $delivery->recipient,
            ]);
            return;
        }

        try {
            $delivery = $this->delivery->fresh();
            if (! $delivery) {
                return;
            }

            if ($delivery->status === 'sending') {
                Log::info('invoice_delivery.send_already_claimed', [
                    'delivery_id' => $delivery->id,
                    'invoice_id' => $delivery->invoice_id,
                    'type' => $delivery->type,
                    'recipient' => $delivery->recipient,
                ]);
                return;
            }

            if ($delivery->status !== 'queued') {
                return;
            }

            $invoice = $delivery->invoice()->with(['client', 'user', 'payments', 'sourcePayments'])->first();
            if (! $invoice || ! $invoice->client) {
                $delivery->update([
                    'status' => 'failed',
                    'error_message' => 'Missing invoice/client context.',
                ]);
                return;
            }

            if ($skipReason = $this->shouldSkipDelivery($delivery, $invoice, $deliveries)) {
                $delivery->update([
                    'status' => 'skipped',
                    'error_message' => $skipReason,
                ]);
                return;
            }

            if ($duplicateReason = $this->duplicateSendReason($delivery)) {
                $delivery->update([
                    'status' => 'skipped',
                    'error_message' => $duplicateReason,
                ]);
                return;
            }

            $claimed = InvoiceDelivery::query()
                ->whereKey($delivery->id)
                ->where('status', 'queued')
                ->update([
                    'status' => 'sending',
                    'error_code' => null,
                    'error_message' => null,
                ]);

            if ($claimed !== 1) {
                return;
            }

            $delivery->refresh();
        } finally {
            $sendLock->release();
        }

        $paymentAcknowledgment = $this->paymentForAcknowledgment($invoice, $delivery);

        $mailable = match ($delivery->type) {
            'payment_acknowledgment_client' => new InvoicePaymentAcknowledgmentClientMail(
                $invoice,
                $delivery,
                $paymentAcknowledgment
            ),
            'payment_acknowledgment_owner' => new InvoicePaymentAcknowledgmentOwnerMail(
                $invoice,
                $delivery,
                $paymentAcknowledgment
            ),
            'receipt' => new InvoicePaidReceiptMail($invoice, $delivery),
            'owner_paid_notice' => new InvoiceOwnerPaidNoticeMail($invoice, $delivery),
            'past_due_owner' => new InvoicePastDueOwnerMail($invoice, $delivery),
            'past_due_client' => new InvoicePastDueClientMail($invoice, $delivery),
            'client_overpay_alert' => new InvoiceOverpaymentClientMail($invoice, $delivery),
            'owner_overpay_alert' => new InvoiceOverpaymentOwnerMail($invoice, $delivery),
            'client_underpay_alert' => new InvoiceUnderpaymentClientMail($invoice, $delivery),
            'owner_underpay_alert' => new InvoiceUnderpaymentOwnerMail($invoice, $delivery),
            'client_partial_warning' => new InvoicePartialWarningClientMail($invoice, $delivery),
            'owner_partial_warning' => new InvoicePartialWarningOwnerMail($invoice, $delivery),
            default => new InvoiceReadyMail($invoice, $delivery),
        };

        try {
            $message = Mail::to($mailAlias->convert($delivery->recipient));
            if ($delivery->cc) {
                $message->cc($mailAlias->convert($delivery->cc));
            }
            $message->send($mailable);

            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);
            Log::info('invoice_delivery.sent', [
                'delivery_id' => $delivery->id,
                'invoice_id' => $invoice->id,
                'type' => $delivery->type,
                'recipient' => $delivery->recipient,
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'error_code' => (string) $e->getCode(),
                'error_message' => $e->getMessage(),
            ]);
            Log::error('Invoice delivery failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function duplicateSendReason(InvoiceDelivery $delivery): ?string
    {
        $query = InvoiceDelivery::query()
            ->where('invoice_id', $delivery->invoice_id)
            ->where('user_id', $delivery->user_id)
            ->where('type', $delivery->type)
            ->whereRaw('LOWER(TRIM(recipient)) = ?', [strtolower(trim($delivery->recipient))])
            ->where('id', '!=', $delivery->id)
            ->whereIn('status', ['sending', 'sent'])
            ->orderByDesc('id');

        $normalizedContextKey = $this->normalizeContextKey($delivery->context_key);

        if ($normalizedContextKey === null) {
            $query->whereNull('context_key');
        } else {
            $query->whereRaw('LOWER(TRIM(context_key)) = ?', [$normalizedContextKey]);
        }

        $existing = $query->first();

        if (! $existing) {
            return null;
        }

        return $existing->status === 'sent'
            ? 'A matching delivery has already been sent.'
            : 'A matching delivery send is already in progress.';
    }

    private function sendLockName(string $intentKey): string
    {
        return 'invoice-delivery-send:' . $intentKey;
    }

    private function shouldSkipDelivery(
        InvoiceDelivery $delivery,
        \App\Models\Invoice $invoice,
        InvoiceDeliveryService $deliveries
    ): ?string
    {
        if ($delivery->status !== 'queued') {
            return 'Delivery no longer queued.';
        }

        if (! $deliveries->outboundEnabled()) {
            return 'Outbound mail is temporarily disabled.';
        }

        if ($delivery->type === 'send') {
            $currentRecipient = trim((string) ($invoice->client?->email ?? ''));
            if ($currentRecipient === '') {
                return 'Client email missing before send.';
            }

            if (strcasecmp($currentRecipient, trim((string) $delivery->recipient)) !== 0) {
                return 'Recipient no longer matches the current client email.';
            }

            if (! $invoice->public_enabled || ! $invoice->public_token) {
                return 'Public share link disabled before send.';
            }
        }

        $paymentAcknowledgmentTypes = ['payment_acknowledgment_client', 'payment_acknowledgment_owner'];
        if (in_array($delivery->type, $paymentAcknowledgmentTypes, true)) {
            $payment = $this->paymentForAcknowledgment($invoice, $delivery);
            if (! $payment) {
                return 'Detected payment no longer matches an active payment on this invoice.';
            }

            if ($delivery->type === 'payment_acknowledgment_client') {
                $currentRecipient = trim((string) ($invoice->client?->email ?? ''));
                if ($currentRecipient === '') {
                    return 'Client email missing before send.';
                }

                if (strcasecmp($currentRecipient, trim((string) $delivery->recipient)) !== 0) {
                    return 'Recipient no longer matches the current client email.';
                }
            }

            if ($delivery->type === 'payment_acknowledgment_owner') {
                $currentRecipient = trim((string) ($invoice->user?->email ?? ''));
                if ($currentRecipient === '') {
                    return 'Owner email missing before send.';
                }

                if (strcasecmp($currentRecipient, trim((string) $delivery->recipient)) !== 0) {
                    return 'Recipient no longer matches the current owner email.';
                }
            }
        }

        $paidTypes = ['receipt', 'owner_paid_notice'];
        if (in_array($delivery->type, $paidTypes, true) && $invoice->status !== 'paid') {
            return 'Invoice no longer paid.';
        }

        $overpayTypes = ['client_overpay_alert', 'owner_overpay_alert'];
        if (in_array($delivery->type, $overpayTypes, true) && !$invoice->requiresClientOverpayAlert()) {
            return 'Overpayment resolved before send.';
        }

        $underpayTypes = ['client_underpay_alert', 'owner_underpay_alert'];
        if (in_array($delivery->type, $underpayTypes, true) && !$invoice->requiresClientUnderpayAlert()) {
            return 'Underpayment resolved before send.';
        }

        $partialTypes = ['client_partial_warning', 'owner_partial_warning'];
        if (in_array($delivery->type, $partialTypes, true) && !$invoice->shouldWarnAboutPartialPayments()) {
            return 'Partial-payment warning no longer applicable.';
        }

        $pastDueTypes = ['past_due_owner', 'past_due_client'];
        if (in_array($delivery->type, $pastDueTypes, true) && in_array($invoice->status, ['paid', 'void'], true)) {
            return 'Invoice settled before past-due alert.';
        }

        return null;
    }

    private function paymentForAcknowledgment(Invoice $invoice, InvoiceDelivery $delivery): ?InvoicePayment
    {
        if (! in_array($delivery->type, ['payment_acknowledgment_client', 'payment_acknowledgment_owner'], true)) {
            return null;
        }

        if (! filled($delivery->context_key)) {
            return null;
        }

        return $invoice->activeSourcePaymentByTxid($delivery->context_key);
    }

    private function normalizeContextKey(?string $contextKey): ?string
    {
        if ($contextKey === null) {
            return null;
        }

        $normalized = strtolower(trim($contextKey));

        return $normalized === '' ? null : $normalized;
    }
}
