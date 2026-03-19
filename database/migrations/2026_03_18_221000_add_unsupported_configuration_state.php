<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_settings', 'unsupported_configuration_active')) {
                $table->boolean('unsupported_configuration_active')->default(false)->after('onboarded_at');
            }

            if (! Schema::hasColumn('wallet_settings', 'unsupported_configuration_source')) {
                $table->string('unsupported_configuration_source', 32)->nullable()->after('unsupported_configuration_active');
            }

            if (! Schema::hasColumn('wallet_settings', 'unsupported_configuration_reason')) {
                $table->string('unsupported_configuration_reason', 64)->nullable()->after('unsupported_configuration_source');
            }

            if (! Schema::hasColumn('wallet_settings', 'unsupported_configuration_details')) {
                $table->text('unsupported_configuration_details')->nullable()->after('unsupported_configuration_reason');
            }

            if (! Schema::hasColumn('wallet_settings', 'unsupported_configuration_flagged_at')) {
                $table->timestamp('unsupported_configuration_flagged_at')->nullable()->after('unsupported_configuration_details');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'unsupported_configuration_flagged')) {
                $table->boolean('unsupported_configuration_flagged')->default(false)->after('wallet_network');
            }

            if (! Schema::hasColumn('invoices', 'unsupported_configuration_source')) {
                $table->string('unsupported_configuration_source', 32)->nullable()->after('unsupported_configuration_flagged');
            }

            if (! Schema::hasColumn('invoices', 'unsupported_configuration_reason')) {
                $table->string('unsupported_configuration_reason', 64)->nullable()->after('unsupported_configuration_source');
            }

            if (! Schema::hasColumn('invoices', 'unsupported_configuration_details')) {
                $table->text('unsupported_configuration_details')->nullable()->after('unsupported_configuration_reason');
            }

            if (! Schema::hasColumn('invoices', 'unsupported_configuration_flagged_at')) {
                $table->timestamp('unsupported_configuration_flagged_at')->nullable()->after('unsupported_configuration_details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'unsupported_configuration_flagged_at')) {
                $table->dropColumn('unsupported_configuration_flagged_at');
            }

            if (Schema::hasColumn('invoices', 'unsupported_configuration_details')) {
                $table->dropColumn('unsupported_configuration_details');
            }

            if (Schema::hasColumn('invoices', 'unsupported_configuration_reason')) {
                $table->dropColumn('unsupported_configuration_reason');
            }

            if (Schema::hasColumn('invoices', 'unsupported_configuration_source')) {
                $table->dropColumn('unsupported_configuration_source');
            }

            if (Schema::hasColumn('invoices', 'unsupported_configuration_flagged')) {
                $table->dropColumn('unsupported_configuration_flagged');
            }
        });

        Schema::table('wallet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_settings', 'unsupported_configuration_flagged_at')) {
                $table->dropColumn('unsupported_configuration_flagged_at');
            }

            if (Schema::hasColumn('wallet_settings', 'unsupported_configuration_details')) {
                $table->dropColumn('unsupported_configuration_details');
            }

            if (Schema::hasColumn('wallet_settings', 'unsupported_configuration_reason')) {
                $table->dropColumn('unsupported_configuration_reason');
            }

            if (Schema::hasColumn('wallet_settings', 'unsupported_configuration_source')) {
                $table->dropColumn('unsupported_configuration_source');
            }

            if (Schema::hasColumn('wallet_settings', 'unsupported_configuration_active')) {
                $table->dropColumn('unsupported_configuration_active');
            }
        });
    }
};
