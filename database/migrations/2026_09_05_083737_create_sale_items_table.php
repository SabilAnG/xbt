<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();

            $table->decimal('qty', 16, 2)->default(1);
            $table->decimal('unit_price', 16, 2)->default(0);
            // Harga pokok yang dibekukan saat nota dibukukan.
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['sale_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
