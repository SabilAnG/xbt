<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer antar dompet (mis. tarik tunai dari BCA ke kas). Satu transfer
 * menjadi dua mutasi: keluar dari dompet asal, masuk ke dompet tujuan.
 * Mutasi manual (setor modal, koreksi) memakai wallet_transactions tanpa sumber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignId('to_wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->decimal('amount', 16, 2);
            $table->date('transferred_at');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');
    }
};
