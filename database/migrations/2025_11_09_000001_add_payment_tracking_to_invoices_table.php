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
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'payment_amount_sat')) {
                $table->unsignedBigInteger('payment_amount_sat')
                    ->nullable()
                    ->after('amount_btc');
            }

            if (!Schema::hasColumn('invoices', 'payment_confirmations')) {
                $table->unsignedInteger('payment_confirmations')
                    ->default(0)
                    ->after('payment_amount_sat');
            }

            if (!Schema::hasColumn('invoices', 'payment_confirmed_height')) {
                $table->unsignedInteger('payment_confirmed_height')
                    ->nullable()
                    ->after('payment_confirmations');
            }

            if (!Schema::hasColumn('invoices', 'payment_detected_at')) {
                $table->timestamp('payment_detected_at')
                    ->nullable()
                    ->after('paid_at');
            }

            if (!Schema::hasColumn('invoices', 'payment_confirmed_at')) {
                $table->timestamp('payment_confirmed_at')
                    ->nullable()
                    ->after('payment_detected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'payment_confirmed_at')) {
                $table->dropColumn('payment_confirmed_at');
            }

            if (Schema::hasColumn('invoices', 'payment_detected_at')) {
                $table->dropColumn('payment_detected_at');
            }

            if (Schema::hasColumn('invoices', 'payment_confirmed_height')) {
                $table->dropColumn('payment_confirmed_height');
            }

            if (Schema::hasColumn('invoices', 'payment_confirmations')) {
                $table->dropColumn('payment_confirmations');
            }

            if (Schema::hasColumn('invoices', 'payment_amount_sat')) {
                $table->dropColumn('payment_amount_sat');
            }
        });
    }
};
