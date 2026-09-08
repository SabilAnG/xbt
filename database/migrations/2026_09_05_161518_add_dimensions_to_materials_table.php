<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fondasi konversi satuan beli -> satuan pakai.
     *
     * Sebelumnya satu bahan hanya punya `unit` (batang) dan `cost_price` per
     * satuan itu, sehingga formula tidak bisa meminta "0,15 meter": pipa dibeli
     * per batang, tapi dipakai per milimeter.
     *
     * Sekarang tiap bahan menyatakan berapa satuan dasar yang didapat dari satu
     * satuan beli, dan harga per satuan dasar diturunkan darinya:
     *
     *   pipa   1 batang = 6.000 mm   -> Rp150.000 / 6.000   = Rp25/mm
     *   plat   1 lembar = 1200x2400  -> Rp600.000 / 2.880.000 = Rp0,208/mm²
     *   gas    1 tabung = 10.000 L   -> Rp300.000 / 10.000  = Rp30/liter
     *
     * `stock` ikut pindah ke satuan dasar supaya sisa potongan bisa dinyatakan
     * (sisa 41,8 m, bukan "6,97 batang"). Aman dilakukan sekarang karena
     * seluruh stok masih 0 dan belum ada satu pun mutasi bahan.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // linear | sheet | weight | volume | count
            $table->string('dimension_type')->default('count')->after('material_category_id');

            // Diisi sesuai dimension_type — berapa satuan dasar per satuan beli.
            $table->decimal('length_mm', 16, 3)->nullable()->after('unit');        // linear
            $table->decimal('sheet_length_mm', 16, 3)->nullable()->after('length_mm'); // sheet
            $table->decimal('sheet_width_mm', 16, 3)->nullable()->after('sheet_length_mm');
            $table->decimal('weight_gram', 16, 3)->nullable()->after('sheet_width_mm');  // weight
            $table->decimal('volume_ml', 16, 3)->nullable()->after('weight_gram');       // volume

            // Spesifikasi pipa/plat — untuk identifikasi, bukan perhitungan.
            $table->decimal('diameter_mm', 10, 2)->nullable()->after('volume_ml');
            $table->decimal('thickness_mm', 10, 2)->nullable()->after('diameter_mm');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn([
                'dimension_type', 'length_mm', 'sheet_length_mm', 'sheet_width_mm',
                'weight_gram', 'volume_ml', 'diameter_mm', 'thickness_mm',
            ]);
        });
    }
};
