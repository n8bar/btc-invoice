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
            $table->string('billing_name_override')->nullable()->after('description');
            $table->string('billing_email_override')->nullable()->after('billing_name_override');
            $table->string('billing_phone_override')->nullable()->after('billing_email_override');
            $table->text('billing_address_override')->nullable()->after('billing_phone_override');
            $table->text('invoice_footer_note_override')->nullable()->after('billing_address_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'billing_name_override',
                'billing_email_override',
                'billing_phone_override',
                'billing_address_override',
                'invoice_footer_note_override',
            ]);
        });
    }
};
