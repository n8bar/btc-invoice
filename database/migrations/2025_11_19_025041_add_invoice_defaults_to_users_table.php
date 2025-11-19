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
            $table->text('invoice_default_description')->nullable()->after('branding_heading');
            $table->unsignedSmallInteger('invoice_default_terms_days')->nullable()->after('invoice_default_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invoice_default_description','invoice_default_terms_days']);
        });
    }
};
