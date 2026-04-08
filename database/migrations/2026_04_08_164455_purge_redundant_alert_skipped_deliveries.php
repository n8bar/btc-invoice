<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            'client_underpay_alert',
            'issuer_underpay_alert',
            'client_overpay_alert',
            'issuer_overpay_alert',
        ];

        $idempotencyMessages = [
            'A matching delivery is already queued.',
            'A matching delivery has already been attempted for this trigger.',
            'A matching delivery has already been sent.',
        ];

        $before = DB::table('invoice_deliveries')
            ->whereIn('type', $types)
            ->where('status', 'skipped')
            ->whereIn('error_message', $idempotencyMessages)
            ->count();

        echo "Purging {$before} redundant skipped alert delivery rows.\n";

        DB::table('invoice_deliveries')
            ->whereIn('type', $types)
            ->where('status', 'skipped')
            ->whereIn('error_message', $idempotencyMessages)
            ->orderBy('id')
            ->chunkById(1000, function ($rows) {
                DB::table('invoice_deliveries')
                    ->whereIn('id', $rows->pluck('id'))
                    ->delete();
            });

        $after = DB::table('invoice_deliveries')
            ->whereIn('type', $types)
            ->where('status', 'skipped')
            ->whereIn('error_message', $idempotencyMessages)
            ->count();

        echo "Done. Rows deleted: " . ($before - $after) . ". Remaining matching rows: {$after}.\n";
    }

    public function down(): void
    {
        // Destructive — deleted rows cannot be restored.
    }
};
