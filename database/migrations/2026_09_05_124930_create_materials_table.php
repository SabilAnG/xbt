<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bahan baku produksi — pipa, plat, inlet, baut.
     *
     * Sengaja terpisah dari tabel `items` (barang siap jual): pipa bukan barang
     * dagangan, dan mencampurnya akan mengotori laporan penjualan yang sudah
     * berjalan.
     *
     * `stock` di sini adalah cache dari material_movements, seperti pola yang
     * dipakai modul inventory barang jual.
     */
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();

            // Satuan beli, mis. batang, lembar, kg, meter, pcs.
            $table->string('unit')->default('pcs');

            // Harga beli terakhir; dipakai formula untuk menghitung HPP.
            $table->decimal('cost_price', 16, 2)->default(0);
            $table->decimal('stock', 16, 3)->default(0);
            $table->decimal('min_stock', 16, 3)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['material_category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
