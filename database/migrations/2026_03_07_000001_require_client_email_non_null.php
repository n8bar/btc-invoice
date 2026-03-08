<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Backfill legacy rows so the non-null change is safe on existing datasets.
        DB::table('clients')
            ->where(function ($query) {
                $query->whereNull('email')
                    ->orWhere('email', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($clients): void {
                foreach ($clients as $client) {
                    DB::table('clients')
                        ->where('id', $client->id)
                        ->update([
                            'email' => "missing-email-client-{$client->id}@example.invalid",
                        ]);
                }
            });

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
        });
    }
};
