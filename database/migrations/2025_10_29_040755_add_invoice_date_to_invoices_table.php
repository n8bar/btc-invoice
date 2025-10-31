<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('invoices','invoice_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('invoice_date')->nullable()->after('number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invoices','invoice_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('invoice_date');
            });
        }
    }
};
