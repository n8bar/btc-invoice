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
            $table->string('branding_heading')->nullable()->after('invoice_footer_note');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('branding_heading_override')->nullable()->after('invoice_footer_note_override');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('branding_heading');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('branding_heading_override');
        });
    }
};
