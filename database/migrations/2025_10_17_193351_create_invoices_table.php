<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();              // e.g. INV-0001
            $table->text('description')->nullable();
            $table->decimal('amount_usd', 12, 2);            // user-entered USD
            $table->decimal('btc_rate', 18, 8);              // USD per BTC at lock
            $table->decimal('amount_btc', 18, 8);            // locked BTC amount
            $table->string('btc_address');                   // static MVP address
            $table->string('status')->default('pending')->index(); // draft|pending|paid|void
            $table->string('txid')->nullable();              // optional note
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoices');
    }
};
