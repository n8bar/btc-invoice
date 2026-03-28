<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Services\InvoiceDeliveryService;
use App\Services\InvoiceAlertService;

class SendInvoiceReceipt
{
    public function __construct(
        private readonly InvoiceAlertService $alerts,
        private readonly InvoiceDeliveryService $deliveries,
    )
    {
    }

    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice->fresh(['client','user']);
        if (!$invoice || !$invoice->client || empty($invoice->client->email)) {
            return;
        }

        if (!$invoice->user || !$invoice->user->auto_receipt_emails) {
            return;
        }

        $this->deliveries->queue($invoice, 'receipt', $invoice->client->email);
        $this->alerts->sendOwnerPaidNotice($invoice);
    }
}
