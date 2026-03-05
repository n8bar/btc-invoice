<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('getting_started_replay_started_at')
                ->nullable()
                ->after('getting_started_dismissed');

            $table->timestamp('getting_started_replay_wallet_verified_at')
                ->nullable()
                ->after('getting_started_replay_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'getting_started_replay_wallet_verified_at',
                'getting_started_replay_started_at',
            ]);
        });
    }
};
