<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $hasGlobalNumberUnique = collect(
            DB::select("SHOW INDEX FROM `invoices` WHERE Key_name = 'invoices_number_unique'")
        )->isNotEmpty();

        if ($hasGlobalNumberUnique) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique('invoices_number_unique');
            });
        }
    }

    public function down(): void
    {
        $hasGlobalNumberUnique = collect(
            DB::select("SHOW INDEX FROM `invoices` WHERE Key_name = 'invoices_number_unique'")
        )->isNotEmpty();

        if (! $hasGlobalNumberUnique) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->unique('number', 'invoices_number_unique');
            });
        }
    }
};
