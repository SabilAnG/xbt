<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mutasi dompet — sumber kebenaran saldo.
     *
     * wallets.current_balance hanyalah cache yang dihitung ulang dari tabel ini,
     * sehingga saldo selalu bisa ditelusuri sampai ke nota asalnya lewat kolom
     * morph source_type/source_id.
     */
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();

            $table->string('direction'); // in | out
            $table->decimal('amount', 16, 2)->default(0);
            $table->decimal('balance_after', 16, 2)->default(0);

            $table->nullableMorphs('source');
            $table->timestamp('occurred_at');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['wallet_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
