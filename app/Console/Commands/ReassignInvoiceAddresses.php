<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\WalletKeyLineage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReassignInvoiceAddresses extends Command
{
    protected $signature = 'wallet:reassign-invoice-addresses
        {--user= : Limit to a specific user ID}
        {--invoice= : Limit to a specific invoice ID}
        {--apply : Persist the corrected addresses (otherwise dry-run)}
        {--include-paid : Include invoices that have payments or are paid/partial}
        {--reset-payments : Remove payment logs and reset status to sent when reassigning paid/partial invoices}
        {--use-next-index : Derive from each wallet\'s cursor ledger and bump indexes forward (instead of reusing invoice derivation_index)}';

    protected $description = 'Re-derive invoice payment addresses using BIP84 external chain and optionally persist them.';

    public function __construct(private readonly WalletKeyLineage $lineage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $includePaid = (bool) $this->option('include-paid');
        $userId = $this->option('user');
        $invoiceId = $this->option('invoice');

        $query = Invoice::query()
            ->with(['user.walletSetting'])
            ->whereNotNull('payment_address')
            ->whereHas('user.walletSetting')
            ->orderBy('id');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($invoiceId) {
            $query->where('id', $invoiceId);
        }

        $summary = [
            'checked' => 0,
            'matched' => 0,
            'updated' => 0,
            'skipped_paid' => 0,
            'errors' => 0,
            'payments_cleared' => 0,
            'wallets_advanced' => 0,
        ];

        $nextIndexes = [];

        $query->chunkById(50, function ($invoices) use (&$summary, $apply, $includePaid, &$nextIndexes) {
            foreach ($invoices as $invoice) {
                $summary['checked']++;
                $wallet = $invoice->user->walletSetting;

                $index = $invoice->derivation_index ?? 0;
                if ($this->option('use-next-index')) {
                    $walletId = $wallet->id;
                    $nextIndexes[$walletId] = $nextIndexes[$walletId]
                        ?? $this->lineage->previewCursor($wallet)['next_derivation_index'];
                    $index = $nextIndexes[$walletId];
                    $nextIndexes[$walletId]++;
                }

                // Avoid touching invoices with recorded payments or paid status.
                if (
                    !$includePaid
                    && (($invoice->payment_amount_sat ?? 0) > 0 || in_array($invoice->status, ['paid', 'partial'], true))
                ) {
                    $summary['skipped_paid']++;
                    $this->line("Invoice {$invoice->id} skipped (paid/partial or has payments). Use --include-paid to override.");
                    continue;
                }

                try {
                    $expectedLineage = $this->lineage->deriveInvoiceLineage($wallet, $index);
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    $this->error("Invoice {$invoice->id} failed derive: {$e->getMessage()}");
                    continue;
                }

                $matchesAddress = $expectedLineage['payment_address'] === $invoice->payment_address;
                $matchesLineage = $expectedLineage['wallet_key_fingerprint'] === $invoice->wallet_key_fingerprint
                    && $expectedLineage['wallet_network'] === $invoice->wallet_network
                    && $index === (int) ($invoice->derivation_index ?? 0);

                if ($matchesAddress && $matchesLineage) {
                    $summary['matched']++;

                    if (
                        $apply
                        && $this->option('reset-payments')
                        && $invoice->payments()->exists()
                    ) {
                        DB::transaction(function () use ($invoice, &$summary) {
                            $invoice->payments()->delete();
                            $invoice->update([
                                'payment_amount_sat' => null,
                                'payment_confirmations' => 0,
                                'payment_confirmed_height' => null,
                                'payment_detected_at' => null,
                                'payment_confirmed_at' => null,
                                'txid' => null,
                                'paid_at' => null,
                                'status' => 'sent',
                            ]);
                        });
                        $summary['payments_cleared']++;
                        $this->line("Invoice {$invoice->id}: payments cleared and status reset to sent.");
                    }

                    continue;
                }

                $this->warn("Invoice {$invoice->id} user {$invoice->user_id}: {$invoice->payment_address} -> {$expectedLineage['payment_address']} (index {$index}, {$wallet->network})");

                if ($apply) {
                    if ($this->option('use-next-index')) {
                        $this->lineage->withNextAssignment($wallet, function (array $assignedLineage) use ($invoice) {
                            $invoice->update($assignedLineage);
                        });
                    } else {
                        DB::transaction(function () use ($invoice, $expectedLineage) {
                            $invoice->update($expectedLineage);
                        });
                    }
                    $summary['updated']++;

                    if ($this->option('reset-payments') && $invoice->payments()->exists()) {
                        DB::transaction(function () use ($invoice, &$summary) {
                            $invoice->payments()->delete();
                            $invoice->update([
                                'payment_amount_sat' => null,
                                'payment_confirmations' => 0,
                                'payment_confirmed_height' => null,
                                'payment_detected_at' => null,
                                'payment_confirmed_at' => null,
                                'txid' => null,
                                'paid_at' => null,
                                'status' => 'sent',
                            ]);
                        });
                        $summary['payments_cleared']++;
                        $this->line(" - Cleared payments and reset status to sent for invoice {$invoice->id}");
                    }
                }
            }
        });

        $this->info(($apply ? 'Applied' : 'Dry run') . ' complete.');
        $this->info(json_encode($summary, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
