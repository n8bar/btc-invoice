<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('accounting_invoice_id')
                ->nullable()
                ->after('ignore_reason')
                ->constrained('invoices');
            $table->timestamp('reattributed_at')->nullable()->after('accounting_invoice_id');
            $table->foreignId('reattributed_by_user_id')
                ->nullable()
                ->after('reattributed_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reattribute_reason', 500)->nullable()->after('reattributed_by_user_id');
        });

        DB::statement('UPDATE invoice_payments SET accounting_invoice_id = invoice_id WHERE ignored_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('accounting_invoice_id');
            $table->dropConstrainedForeignId('reattributed_by_user_id');
            $table->dropColumn(['reattributed_at', 'reattribute_reason']);
        });
    }
};
