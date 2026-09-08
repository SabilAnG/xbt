<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahan yang dibutuhkan sebuah formula.
     *
     * `qty` adalah kebutuhan untuk SATU KALI resep (menghasilkan
     * formulas.output_qty unit), bukan per unit. Pembagian ke per-unit
     * dilakukan saat menghitung HPP.
     */
    public function up(): void
    {
        Schema::create('formula_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();

            $table->decimal('qty', 16, 3)->default(0);

            // Susut/waste dalam persen, mis. potongan pipa yang terbuang.
            $table->decimal('waste_percent', 8, 2)->default(0);

            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['formula_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_materials');
    }
};
