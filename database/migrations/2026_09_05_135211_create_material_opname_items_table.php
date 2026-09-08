<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('rack_id')->nullable()->constrained()->nullOnDelete();

            // Stok menurut sistem saat baris dibuat, dibekukan supaya selisih
            // tetap terbaca walau stok berubah setelah opname dibukukan.
            $table->decimal('system_qty', 16, 3)->default(0);
            $table->decimal('physical_qty', 16, 3)->default(0);
            $table->decimal('difference', 16, 3)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['material_opname_id', 'material_id', 'rack_id'], 'mat_opname_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_opname_items');
    }
};
