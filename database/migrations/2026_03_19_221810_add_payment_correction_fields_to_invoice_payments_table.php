<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->timestamp('ignored_at')->nullable()->after('note');
            $table->foreignId('ignored_by_user_id')
                ->nullable()
                ->after('ignored_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('ignore_reason', 500)->nullable()->after('ignored_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ignored_by_user_id');
            $table->dropColumn(['ignored_at', 'ignore_reason']);
        });
    }
};
