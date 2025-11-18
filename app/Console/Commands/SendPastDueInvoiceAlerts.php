<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceAlertService;
use Illuminate\Console\Command;

class SendPastDueInvoiceAlerts extends Command
{
    protected $signature = 'invoices:send-past-due-alerts';

    protected $description = 'Email owners and clients about past-due invoices.';

    public function __construct(private readonly InvoiceAlertService $alerts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        Invoice::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['paid','void'])
            ->with(['user','client','payments'])
            ->chunkById(100, function ($invoices) use (&$count) {
                foreach ($invoices as $invoice) {
                    $this->alerts->sendPastDueAlerts($invoice);
                    $count++;
                }
            });

        $this->info("Checked {$count} invoices for past-due alerts.");

        return Command::SUCCESS;
    }
}
