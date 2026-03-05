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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_overpayment_gratuity_note')->default(true)->after('auto_receipt_emails');
            $table->boolean('show_qr_refresh_reminder')->default(true)->after('show_overpayment_gratuity_note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'show_overpayment_gratuity_note',
                'show_qr_refresh_reminder',
            ]);
        });
    }
};

