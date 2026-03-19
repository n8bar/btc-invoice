<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_key_cursors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 16);
            $table->string('key_fingerprint', 64);
            $table->unsignedBigInteger('next_derivation_index')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'network', 'key_fingerprint']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'wallet_key_fingerprint')) {
                $table->string('wallet_key_fingerprint', 64)->nullable()->after('derivation_index');
            }

            if (! Schema::hasColumn('invoices', 'wallet_network')) {
                $table->string('wallet_network', 16)->nullable()->after('wallet_key_fingerprint');
            }
        });

        Schema::table('wallet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_settings', 'next_derivation_index')) {
                $table->dropColumn('next_derivation_index');
            }
        });

        Schema::table('user_wallet_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('user_wallet_accounts', 'next_derivation_index')) {
                $table->dropColumn('next_derivation_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_wallet_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('user_wallet_accounts', 'next_derivation_index')) {
                $table->unsignedBigInteger('next_derivation_index')->default(0)->after('bip84_xpub');
            }
        });

        Schema::table('wallet_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_settings', 'next_derivation_index')) {
                $table->unsignedBigInteger('next_derivation_index')->default(0)->after('bip84_xpub');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'wallet_network')) {
                $table->dropColumn('wallet_network');
            }

            if (Schema::hasColumn('invoices', 'wallet_key_fingerprint')) {
                $table->dropColumn('wallet_key_fingerprint');
            }
        });

        Schema::dropIfExists('wallet_key_cursors');
    }
};
