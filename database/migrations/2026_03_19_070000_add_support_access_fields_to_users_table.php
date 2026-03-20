<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'support_access_granted_at')) {
                $table->timestamp('support_access_granted_at')->nullable()->after('show_qr_refresh_reminder');
            }

            if (! Schema::hasColumn('users', 'support_access_expires_at')) {
                $table->timestamp('support_access_expires_at')->nullable()->after('support_access_granted_at');
            }

            if (! Schema::hasColumn('users', 'support_access_terms_version')) {
                $table->string('support_access_terms_version', 32)->nullable()->after('support_access_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['support_access_terms_version', 'support_access_expires_at', 'support_access_granted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
