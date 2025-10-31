<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices','public_token')) {
                $table->string('public_token', 64)->nullable()->unique()->after('status');
            }
            if (!Schema::hasColumn('invoices','public_enabled')) {
                $table->boolean('public_enabled')->default(false)->after('public_token');
            }
            if (!Schema::hasColumn('invoices','public_expires_at')) {
                $table->timestamp('public_expires_at')->nullable()->after('public_enabled');
            }
        });
    }
    public function down(): void {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices','public_expires_at')) $table->dropColumn('public_expires_at');
            if (Schema::hasColumn('invoices','public_enabled'))    $table->dropColumn('public_enabled');
            if (Schema::hasColumn('invoices','public_token'))      $table->dropUnique(['public_token']);
            if (Schema::hasColumn('invoices','public_token'))      $table->dropColumn('public_token');
        });
    }
};
