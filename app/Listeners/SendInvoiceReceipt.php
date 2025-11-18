<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Jobs\DeliverInvoiceMail;
use App\Models\InvoiceDelivery;

class SendInvoiceReceipt
{
    /**
     * Handle the event.
     */
    public function handle(InvoicePaid $event): void
    {
            $invoice = $event->invoice->fresh(['client','user']);
            if (!$invoice || !$invoice->client || empty($invoice->client->email)) {
                return;
            }

            if (!$invoice->user || !$invoice->user->auto_receipt_emails) {
                return;
            }

            $alreadySent = $invoice->deliveries()
                ->where('type', 'receipt')
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                return;
            }

            $delivery = InvoiceDelivery::create([
                'invoice_id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'type' => 'receipt',
                'status' => 'queued',
                'recipient' => $invoice->client->email,
                'dispatched_at' => now(),
            ]);

            DeliverInvoiceMail::dispatch($delivery);
    }
}
