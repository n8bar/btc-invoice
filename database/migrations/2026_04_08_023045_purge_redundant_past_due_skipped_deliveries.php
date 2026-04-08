<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delete skipped past-due delivery rows where a sent row already exists
     * for the same invoice + type + recipient + context_key combination.
     * These rows are pure noise accumulated by the pre-fix cron loop.
     */
    public function up(): void
    {
        $count = DB::table('invoice_deliveries as skipped')
            ->whereIn('skipped.type', ['past_due_issuer', 'past_due_client'])
            ->where('skipped.status', 'skipped')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoice_deliveries as sent')
                    ->where('sent.status', 'sent')
                    ->whereColumn('sent.invoice_id', 'skipped.invoice_id')
                    ->whereColumn('sent.type', 'skipped.type')
                    ->whereRaw('LOWER(TRIM(sent.recipient)) = LOWER(TRIM(skipped.recipient))')
                    ->whereRaw('LOWER(TRIM(sent.context_key)) = LOWER(TRIM(skipped.context_key))');
            })
            ->count();

        echo "Purging {$count} redundant skipped past-due delivery rows.\n";

        // MySQL does not allow deleting from a table referenced in a subquery,
        // so collect IDs first then delete by primary key.
        $ids = DB::table('invoice_deliveries as skipped')
            ->select('skipped.id')
            ->whereIn('skipped.type', ['past_due_issuer', 'past_due_client'])
            ->where('skipped.status', 'skipped')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('invoice_deliveries as sent')
                    ->where('sent.status', 'sent')
                    ->whereColumn('sent.invoice_id', 'skipped.invoice_id')
                    ->whereColumn('sent.type', 'skipped.type')
                    ->whereRaw('LOWER(TRIM(sent.recipient)) = LOWER(TRIM(skipped.recipient))')
                    ->whereRaw('LOWER(TRIM(sent.context_key)) = LOWER(TRIM(skipped.context_key))');
            })
            ->pluck('skipped.id');

        DB::table('invoice_deliveries')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        // Purged rows cannot be restored.
    }
};
