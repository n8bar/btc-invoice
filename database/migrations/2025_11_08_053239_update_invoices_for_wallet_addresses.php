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
            if (!Schema::hasColumn('invoices', 'payment_address')) {
                $table->string('payment_address')->after('amount_btc')->nullable();
            }
            if (!Schema::hasColumn('invoices', 'derivation_index')) {
                $table->unsignedBigInteger('derivation_index')->after('payment_address')->nullable();
            }
            if (Schema::hasColumn('invoices', 'btc_address')) {
                $table->dropColumn('btc_address');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'btc_address')) {
                $table->dropColumn('btc_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'btc_address')) {
                $table->string('btc_address')->nullable()->after('email');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'btc_address')) {
                $table->string('btc_address')->nullable()->after('amount_btc');
            }
            if (Schema::hasColumn('invoices', 'payment_address')) {
                $table->dropColumn('payment_address');
            }
            if (Schema::hasColumn('invoices', 'derivation_index')) {
                $table->dropColumn('derivation_index');
            }
        });
    }
};
