<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoicePaymentSyncService;
use Illuminate\Console\Command;

class WatchInvoicePayments extends Command
{
    protected $signature = 'wallet:watch-payments {--invoice= : Limit to a specific invoice ID}';

    protected $description = 'Poll known invoice addresses for payments and auto-mark invoices paid.';

    public function __construct(
        private readonly InvoicePaymentSyncService $syncService
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Invoice::query()
            ->whereNotNull('payment_address')
            ->where('status', '!=', 'void')
            ->orderBy('id');

        if ($invoiceId = $this->option('invoice')) {
            $query->where('id', $invoiceId);
        }

        $processed = 0;
        $updated = 0;

        $query->chunkById(50, function ($invoices) use (&$processed, &$updated) {
            foreach ($invoices as $invoice) {
                $processed++;
                if (! $invoice->wallet_network || ! $invoice->wallet_key_fingerprint) {
                    $this->warn("Invoice {$invoice->id} lacks wallet lineage and was skipped.");
                    continue;
                }

                $result = $this->syncService->syncInvoice(
                    $invoice,
                    network: $invoice->wallet_network,
                    force: true,
                    checkAlerts: true
                );

                if (! $result['processed']) {
                    continue;
                }

                $updated += $result['updated'];

                foreach ($result['payments'] as $payment) {
                    $status = strtoupper($result['status'] ?? 'sent');
                    $this->info(
                        "Invoice {$invoice->id} {$status}: {$payment['txid']} ({$payment['sats']} sats, total {$result['paid_sats']})."
                    );
                }
            }
        });

        $this->info("Processed {$processed} invoices, updated {$updated}.");

        return Command::SUCCESS;
    }
}
