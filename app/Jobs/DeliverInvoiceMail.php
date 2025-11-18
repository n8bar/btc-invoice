<?php

namespace App\Jobs;

use App\Mail\InvoicePaidReceiptMail;
use App\Mail\InvoiceReadyMail;
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

        $invoice = $delivery->invoice()->with(['client','user'])->first();
        if (!$invoice || !$invoice->client) {
            $delivery->update([
                'status' => 'failed',
                'error_message' => 'Missing invoice/client context.',
            ]);
            return;
        }

        $mailable = match ($delivery->type) {
            'receipt' => new InvoicePaidReceiptMail($invoice, $delivery),
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
