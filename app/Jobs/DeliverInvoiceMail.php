<?php

namespace App\Jobs;

use App\Mail\InvoiceOverpaymentClientMail;
use App\Mail\InvoiceOverpaymentOwnerMail;
use App\Mail\InvoicePastDueClientMail;
use App\Mail\InvoicePastDueOwnerMail;
use App\Mail\InvoicePaidReceiptMail;
use App\Mail\InvoiceReadyMail;
use App\Mail\InvoiceUnderpaymentClientMail;
use App\Mail\InvoiceUnderpaymentOwnerMail;
use App\Mail\InvoiceOwnerPaidNoticeMail;
use App\Mail\InvoicePartialWarningClientMail;
use App\Mail\InvoicePartialWarningOwnerMail;
use App\Models\InvoiceDelivery;
use App\Services\MailAlias;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DeliverInvoiceMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public InvoiceDelivery $delivery)
    {
    }

    public function handle(MailAlias $mailAlias): void
    {
        $delivery = $this->delivery->fresh();
        if (!$delivery) {
            return;
        }

        $invoice = $delivery->invoice()->with(['client','user','payments'])->first();
        if (!$invoice || !$invoice->client) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => 'Missing invoice/client context.',
            ]);
            return;
        }

        $mailable = match ($delivery->type) {
            'receipt' => new InvoicePaidReceiptMail($invoice, $delivery),
            'owner_paid_notice' => new InvoiceOwnerPaidNoticeMail($invoice, $delivery),
            'past_due_owner' => new InvoicePastDueOwnerMail($invoice, $delivery),
            'past_due_client' => new InvoicePastDueClientMail($invoice, $delivery),
            'client_overpay_alert' => new InvoiceOverpaymentClientMail($invoice, $delivery),
            'owner_overpay_alert' => new InvoiceOverpaymentOwnerMail($invoice, $delivery),
            'client_underpay_alert' => new InvoiceUnderpaymentClientMail($invoice, $delivery),
            'owner_underpay_alert' => new InvoiceUnderpaymentOwnerMail($invoice, $delivery),
            'client_partial_warning' => new \App\Mail\InvoicePartialWarningClientMail($invoice, $delivery),
            'owner_partial_warning' => new \App\Mail\InvoicePartialWarningOwnerMail($invoice, $delivery),
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
}
