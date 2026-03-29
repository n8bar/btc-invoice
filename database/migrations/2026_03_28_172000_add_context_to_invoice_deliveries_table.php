<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->string('context_key', 191)->nullable()->after('type');
            $table->json('meta')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->dropColumn(['context_key', 'meta']);
        });
    }
};
