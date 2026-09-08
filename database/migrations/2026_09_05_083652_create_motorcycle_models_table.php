<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Type motor — nama model di bawah sebuah brand, mis. Vario 160, Sportster.
     */
    public function up(): void
    {
        Schema::create('motorcycle_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('motorcycle_brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            // Rentang tahun produksi, dipakai saat mencocokkan kecocokan knalpot.
            $table->year('year_from')->nullable();
            $table->year('year_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['motorcycle_brand_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_models');
    }
};
