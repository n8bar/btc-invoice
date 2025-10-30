<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add unique index only if it isn't already present
        $has = collect(DB::select("SHOW INDEX FROM `invoices` WHERE Key_name = 'invoices_user_id_number_unique'"))->isNotEmpty();
        if (!$has) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique(['user_id','number'], 'invoices_user_id_number_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop it if present
        $has = collect(DB::select("SHOW INDEX FROM `invoices` WHERE Key_name = 'invoices_user_id_number_unique'"))->isNotEmpty();
        if ($has) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_user_id_number_unique');
            });
        }
    }
};
