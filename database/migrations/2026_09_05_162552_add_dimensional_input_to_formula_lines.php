<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Input formula jadi dimensional, bukan angka satuan dasar mentah.
     *
     * Sebelumnya plat body harus diisi "103680" (mm²). Sekarang diisi apa
     * adanya — 324 x 320 x 1 potong — dan sistem yang menghitung luasnya.
     * Endcap cukup diisi Ø110 x 2 potong.
     *
     * `qty` tetap ada dan tetap berisi satuan dasar, tapi kini DITURUNKAN dari
     * kolom-kolom di bawah, bukan diketik. Dengan begitu seluruh perhitungan
     * HPP, kapasitas, dan konsumsi produksi yang sudah jalan tidak perlu diubah.
     *
     * bom_group memecah resep jadi bagian: Header, Silencer, Mounting,
     * Finishing — sesuai struktur kerja bengkel.
     */
    public function up(): void
    {
        Schema::table('formula_materials', function (Blueprint $table) {
            $table->string('bom_group')->default('other')->after('material_id');

            // length | rect | circle | direct
            $table->string('input_mode')->default('direct')->after('bom_group');

            $table->decimal('piece_length_mm', 16, 3)->nullable()->after('input_mode');
            $table->decimal('piece_width_mm', 16, 3)->nullable()->after('piece_length_mm');
            $table->decimal('piece_diameter_mm', 16, 3)->nullable()->after('piece_width_mm');
            $table->decimal('piece_count', 16, 3)->default(1)->after('piece_diameter_mm');

            $table->index('bom_group');
        });

        Schema::table('formula_costs', function (Blueprint $table) {
            $table->string('bom_group')->default('finishing')->after('cost_component_id');
            $table->index('bom_group');
        });
    }

    public function down(): void
    {
        Schema::table('formula_materials', function (Blueprint $table) {
            $table->dropIndex(['bom_group']);
            $table->dropColumn([
                'bom_group', 'input_mode', 'piece_length_mm',
                'piece_width_mm', 'piece_diameter_mm', 'piece_count',
            ]);
        });

        Schema::table('formula_costs', function (Blueprint $table) {
            $table->dropIndex(['bom_group']);
            $table->dropColumn('bom_group');
        });
    }
};
