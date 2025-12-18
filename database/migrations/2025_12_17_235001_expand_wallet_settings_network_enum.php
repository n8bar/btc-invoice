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

        DB::statement(
            "ALTER TABLE `wallet_settings` MODIFY `network` ENUM('testnet','testnet3','testnet4','mainnet') NOT NULL DEFAULT 'testnet'"
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            "ALTER TABLE `wallet_settings` MODIFY `network` ENUM('testnet','mainnet') NOT NULL DEFAULT 'testnet'"
        );
    }
};
