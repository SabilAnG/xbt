<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();

            // Stok menurut sistem saat baris dibuat, dibekukan supaya selisih
            // tetap terbaca walau stok berubah setelah opname dibukukan.
            $table->decimal('system_qty', 16, 2)->default(0);
            $table->decimal('physical_qty', 16, 2)->default(0);
            $table->decimal('difference', 16, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'item_id'], 'opname_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
    }
};
