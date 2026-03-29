<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Services\InvoiceAlertService;

class SendInvoiceReceipt
{
    public function __construct(
        private readonly InvoiceAlertService $alerts
    )
    {
    }

    public function handle(InvoicePaid $event): void
    {
        $invoice = $event->invoice->fresh(['user']);
        if (!$invoice) {
            return;
        }

        $this->alerts->sendOwnerPaidNotice($invoice);
    }
}
