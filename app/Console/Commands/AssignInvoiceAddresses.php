<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\User;
use App\Services\HdWallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignInvoiceAddresses extends Command
{
    protected $signature = 'wallet:assign-invoice-addresses {--dry-run : Output what would change without writing}';

    protected $description = 'Assign derived payment addresses to legacy invoices lacking one.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $hdWallet = app(HdWallet::class);
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

            foreach ($invoices as $invoice) {
                $address = $hdWallet->deriveAddress(
                    $wallet->bip84_xpub,
                    $wallet->next_derivation_index,
                    $wallet->network
                );

                $this->line(" - Invoice {$invoice->id} gets {$address} (index {$wallet->next_derivation_index})");

                if (!$dryRun) {
                    DB::transaction(function () use ($invoice, $wallet, $address) {
                        $invoice->update([
                            'payment_address' => $address,
                            'derivation_index' => $wallet->next_derivation_index,
                        ]);
                        $wallet->increment('next_derivation_index');
                    });
                } else {
                    $wallet->next_derivation_index++;
                }

                $total++;
            }
        }

        $this->info($dryRun ? "Dry run complete: {$total} invoices would change." : "Assigned addresses for {$total} invoices.");

        return Command::SUCCESS;
    }
}
