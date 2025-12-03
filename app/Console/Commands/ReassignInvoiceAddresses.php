<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\HdWallet;
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
        {--use-next-index : Derive from each wallet\'s next_derivation_index and bump indexes forward (instead of reusing invoice derivation_index)}';

    protected $description = 'Re-derive invoice payment addresses using BIP84 external chain and optionally persist them.';

    public function __construct(private readonly HdWallet $wallet)
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
        $walletsTouched = [];

        $query->chunkById(50, function ($invoices) use (&$summary, $apply, $includePaid, &$nextIndexes, &$walletsTouched) {
            foreach ($invoices as $invoice) {
                $summary['checked']++;
                $wallet = $invoice->user->walletSetting;

                $index = $invoice->derivation_index ?? 0;
                if ($this->option('use-next-index')) {
                    $walletId = $wallet->id;
                    $nextIndexes[$walletId] = $nextIndexes[$walletId] ?? $wallet->next_derivation_index;
                    $index = $nextIndexes[$walletId];
                    $nextIndexes[$walletId]++;
                    $walletsTouched[$walletId] = $wallet;
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
                    $expected = $this->wallet->deriveAddress(
                        $wallet->bip84_xpub,
                        $index,
                        $wallet->network
                    );
                } catch (\Throwable $e) {
                    $summary['errors']++;
                    $this->error("Invoice {$invoice->id} failed derive: {$e->getMessage()}");
                    continue;
                }

                if ($expected === $invoice->payment_address) {
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

                $this->warn("Invoice {$invoice->id} user {$invoice->user_id}: {$invoice->payment_address} -> {$expected} (index {$index}, {$wallet->network})");

                if ($apply) {
                    DB::transaction(function () use ($invoice, $expected, &$summary, $index) {
                        $invoice->update([
                            'payment_address' => $expected,
                            'derivation_index' => $index,
                        ]);
                    });
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

        if ($apply && $this->option('use-next-index') && !empty($walletsTouched)) {
            foreach ($walletsTouched as $walletId => $wallet) {
                $next = $nextIndexes[$walletId] ?? $wallet->next_derivation_index;
                if ($next !== $wallet->next_derivation_index) {
                    $wallet->next_derivation_index = $next;
                    $wallet->save();
                    $summary['wallets_advanced']++;
                    $this->line("Advanced wallet {$wallet->id} next_derivation_index to {$next}.");
                }
            }
        }

        $this->info(($apply ? 'Applied' : 'Dry run') . ' complete.');
        $this->info(json_encode($summary, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
