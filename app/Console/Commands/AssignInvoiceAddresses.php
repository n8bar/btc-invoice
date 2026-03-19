<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Services\WalletKeyLineage;
use Illuminate\Console\Command;

class AssignInvoiceAddresses extends Command
{
    protected $signature = 'wallet:assign-invoice-addresses {--dry-run : Output what would change without writing}';

    protected $description = 'Assign derived payment addresses to legacy invoices lacking one.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $lineage = app(WalletKeyLineage::class);
        $total = 0;

        $users = User::with('walletSetting')->get();
        foreach ($users as $user) {
            $wallet = $user->walletSetting;
            if (!$wallet) {
                $this->warn("User {$user->id} ({$user->email}) has invoices but no wallet settings.");
                continue;
            }

            $invoices = Invoice::where('user_id', $user->id)
                ->whereNull('payment_address')
                ->orderBy('id')
                ->get();

            if ($invoices->isEmpty()) {
                continue;
            }

            $this->info("Assigning addresses for user {$user->id} ({$user->email})");
            $preview = $lineage->previewCursor($wallet);
            $nextIndex = $preview['next_derivation_index'];

            foreach ($invoices as $invoice) {
                $draftLineage = $dryRun
                    ? $lineage->deriveInvoiceLineage($wallet, $nextIndex)
                    : null;
                $line = $draftLineage ?? [];
                $index = $dryRun ? $nextIndex : null;

                if ($dryRun) {
                    $this->line(" - Invoice {$invoice->id} gets {$line['payment_address']} (index {$index})");
                }

                if (!$dryRun) {
                    $lineage->withNextAssignment($wallet, function (array $assignedLineage) use ($invoice) {
                        $invoice->update($assignedLineage);
                    });
                } else {
                    $nextIndex++;
                }

                $total++;
            }
        }

        $this->info($dryRun ? "Dry run complete: {$total} invoices would change." : "Assigned addresses for {$total} invoices.");

        return Command::SUCCESS;
    }
}
