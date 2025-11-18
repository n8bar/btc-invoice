<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('last_overpayment_alert_at')->nullable()->after('payment_confirmed_at');
            $table->timestamp('last_underpayment_alert_at')->nullable()->after('last_overpayment_alert_at');
            $table->timestamp('last_past_due_owner_alert_at')->nullable()->after('last_underpayment_alert_at');
            $table->timestamp('last_past_due_client_alert_at')->nullable()->after('last_past_due_owner_alert_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'last_overpayment_alert_at',
                'last_underpayment_alert_at',
                'last_past_due_owner_alert_at',
                'last_past_due_client_alert_at',
            ]);
        });
    }
};
