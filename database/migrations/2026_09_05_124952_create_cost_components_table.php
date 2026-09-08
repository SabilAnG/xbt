<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Komponen biaya produksi selain bahan — jasa las, bubut, poles, bending.
     *
     * `rate` adalah tarif per satuan (mis. per titik las, per jam, per unit).
     * Formula menyimpan berapa banyak satuan yang dipakai, lalu HPP dihitung
     * dari qty x rate.
     */
    public function up(): void
    {
        Schema::create('cost_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // jasa | overhead | tenaga_kerja
            $table->string('type')->default('jasa');

            // Satuan tarif: titik, jam, unit, set.
            $table->string('unit')->default('unit');
            $table->decimal('rate', 16, 2)->default(0);

            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_components');
    }
};
