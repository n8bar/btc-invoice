<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mail_brand_name')->nullable()->after('branding_heading');
            $table->string('mail_brand_tagline')->nullable()->after('mail_brand_name');
            $table->text('mail_footer_blurb')->nullable()->after('mail_brand_tagline');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'mail_brand_name',
                'mail_brand_tagline',
                'mail_footer_blurb',
            ]);
        });
    }
};
