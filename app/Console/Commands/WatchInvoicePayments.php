<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoicePaymentDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WatchInvoicePayments extends Command
{
    protected $signature = 'wallet:watch-payments {--invoice= : Limit to a specific invoice ID}';

    protected $description = 'Poll known invoice addresses for payments and auto-mark invoices paid.';

    public function __construct(private readonly InvoicePaymentDetector $detector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Invoice::query()
            ->with(['user.walletSetting'])
            ->whereNotNull('payment_address')
            ->whereHas('user.walletSetting')
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
                $wallet = $invoice->user->walletSetting;
                if (!$wallet) {
                    $this->warn("Invoice {$invoice->id} has no wallet setting.");
                    continue;
                }

                $result = $this->detector->detect($invoice, $wallet->network);
                if (!$result) {
                    continue;
                }

                $this->applyDetection($invoice, $result);
                $updated++;
                $this->info("Invoice {$invoice->id} marked paid via {$result['txid']} ({$result['sats']} sats).");
            }
        });

        $this->info("Processed {$processed} invoices, updated {$updated}.");

        return Command::SUCCESS;
    }

    private function applyDetection(Invoice $invoice, array $result): void
    {
        $updates = [
            'txid' => $result['txid'],
            'payment_amount_sat' => $result['sats'],
            'payment_confirmations' => $result['confirmations'],
            'payment_confirmed_height' => $result['block_height'],
        ];

        if (!$invoice->payment_detected_at) {
            $updates['payment_detected_at'] = $result['detected_at'];
        }

        if ($result['confirmed'] && ($result['confirmed_at'] ?? null)) {
            $updates['payment_confirmed_at'] = $result['confirmed_at'];
        }

        $requiredConfs = (int) config('blockchain.confirmations_required', 1);
        if ($result['confirmations'] >= $requiredConfs) {
            $updates['status'] = 'paid';
            $updates['paid_at'] = $result['confirmed_at'] ?? $result['detected_at'];
        } elseif ($invoice->status !== 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = $result['detected_at'];
        }

        DB::transaction(function () use ($invoice, $updates) {
            $invoice->fill($updates)->save();
        });
    }
}
