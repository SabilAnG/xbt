<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok bahan per rak.
     *
     * materials.stock menyimpan total keseluruhan; tabel ini memecahnya per
     * lokasi sehingga terlihat bahan yang sama tersebar di rak mana saja.
     */
    public function up(): void
    {
        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rack_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 16, 3)->default(0);
            $table->timestamps();

            $table->unique(['material_id', 'rack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stocks');
    }
};
