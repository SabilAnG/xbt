<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kartu stok — satu baris per pergerakan barang.
     *
     * Ini sumber kebenaran stok; items.stock hanya cache yang dihitung ulang
     * dari tabel ini. Kolom morph (source_type/source_id) menunjuk ke nota
     * pembelian, penjualan, atau stok opname yang memicunya.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->string('type'); // purchase | sale | opname | adjustment
            $table->decimal('qty_in', 16, 2)->default(0);
            $table->decimal('qty_out', 16, 2)->default(0);
            $table->decimal('balance_after', 16, 2)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);

            $table->nullableMorphs('source');
            $table->timestamp('moved_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'moved_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
