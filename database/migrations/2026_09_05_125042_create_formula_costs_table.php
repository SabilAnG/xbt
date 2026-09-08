<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen biaya (jasa las, bubut, poles) yang dipakai sebuah formula.
     *
     * Seperti formula_materials, `qty` berlaku untuk satu kali resep.
     * Tarifnya diambil dari cost_components.rate saat menghitung, sehingga
     * kenaikan ongkos las langsung tercermin di HPP semua formula.
     */
    public function up(): void
    {
        Schema::create('formula_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_component_id')->constrained()->restrictOnDelete();

            $table->decimal('qty', 16, 3)->default(1);
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['formula_id', 'cost_component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_costs');
    }
};
