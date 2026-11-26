<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Foto bukti stok fisik dari stok opname (produk jadi, bahan, atau batang pipa). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_photos', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->date('taken_at');
            $table->string('counted_by', 100)->nullable();
            $table->decimal('counted_qty', 16, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_photos');
    }
};
