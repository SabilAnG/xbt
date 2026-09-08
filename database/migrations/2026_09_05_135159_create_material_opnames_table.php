<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok opname bahan baku — hitung fisik gudang produksi.
     *
     * Terpisah dari `stock_opnames` (barang jual) karena bahan dihitung per rak
     * dan satuannya pecahan (0.5 batang, 2.25 kg), sementara barang jual dihitung
     * per unit utuh.
     */
    public function up(): void
    {
        Schema::create('material_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date');
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('counted_by')->nullable();

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'opname_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_opnames');
    }
};
