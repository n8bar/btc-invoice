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
            $table->timestamp('getting_started_completed_at')
                ->nullable()
                ->after('theme');
            $table->boolean('getting_started_dismissed')
                ->default(false)
                ->after('getting_started_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'getting_started_dismissed',
                'getting_started_completed_at',
            ]);
        });
    }
};
