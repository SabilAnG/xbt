<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();

            // Rak tujuan penyimpanan; menentukan ke lokasi mana stok bertambah.
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('qty', 16, 3)->default(1);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['material_purchase_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_purchase_items');
    }
};
