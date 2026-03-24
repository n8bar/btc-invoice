<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['accounting_invoice_id']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['accounting_invoice_id']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('accounting_invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
