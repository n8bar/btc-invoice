<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->after('error_message');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_deliveries', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn('provider_message_id');
        });
    }
};
