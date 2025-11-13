<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('txid', 191);
            $table->unsignedInteger('vout_index')->nullable();
            $table->unsignedBigInteger('sats_received');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedInteger('block_height')->nullable();
            $table->decimal('usd_rate', 16, 2)->nullable();
            $table->decimal('fiat_amount', 16, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id','txid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
