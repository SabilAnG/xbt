<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kartu stok bahan baku — sumber kebenaran stok produksi.
     *
     * materials.stock dan material_stocks.qty hanyalah cache yang dihitung
     * ulang dari tabel ini. Kolom morph menunjuk ke nota pembelian bahan atau
     * order produksi yang memicunya.
     */
    public function up(): void
    {
        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();

            // purchase | production | adjustment | opname
            $table->string('type');
            $table->decimal('qty_in', 16, 3)->default(0);
            $table->decimal('qty_out', 16, 3)->default(0);
            $table->decimal('balance_after', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);

            $table->nullableMorphs('source');
            $table->timestamp('moved_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['material_id', 'moved_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_movements');
    }
};
