<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahan yang benar-benar dipakai sebuah order produksi.
     *
     * Disalin dari formula saat nota dibuat, lalu boleh disesuaikan bila
     * pemakaian nyata berbeda dari resep. Harga satuan dibekukan saat
     * pembukuan.
     */
    public function up(): void
    {
        Schema::create('production_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('qty', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['production_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_materials');
    }
};
