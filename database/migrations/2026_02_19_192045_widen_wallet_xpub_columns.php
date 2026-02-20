<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('wallet_settings') && Schema::hasColumn('wallet_settings', 'bip84_xpub')) {
            DB::statement('ALTER TABLE `wallet_settings` MODIFY `bip84_xpub` TEXT NOT NULL');
        }

        if (Schema::hasTable('user_wallet_accounts') && Schema::hasColumn('user_wallet_accounts', 'bip84_xpub')) {
            DB::statement('ALTER TABLE `user_wallet_accounts` MODIFY `bip84_xpub` TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('wallet_settings') && Schema::hasColumn('wallet_settings', 'bip84_xpub')) {
            DB::statement('ALTER TABLE `wallet_settings` MODIFY `bip84_xpub` VARCHAR(255) NOT NULL');
        }

        if (Schema::hasTable('user_wallet_accounts') && Schema::hasColumn('user_wallet_accounts', 'bip84_xpub')) {
            DB::statement('ALTER TABLE `user_wallet_accounts` MODIFY `bip84_xpub` VARCHAR(255) NOT NULL');
        }
    }
};
