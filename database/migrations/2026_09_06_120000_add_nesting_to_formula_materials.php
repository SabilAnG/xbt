<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perhitungan plat dengan nesting.
 *
 * Luas potongan dibagi luas lembaran adalah hitungan di atas kertas: di
 * bengkel, potongan 324x320 dari lembaran 2400x1200 meninggalkan sisa yang
 * tidak terpakai. Dengan nesting, yang dibebankan ke HPP adalah luas lembaran
 * dibagi jumlah potongan yang benar-benar muat.
 *
 * Bisa dimatikan per baris untuk potongan yang memang diambil dari sisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formula_materials', function (Blueprint $table) {
            $table->boolean('use_nesting')->default(true)->after('waste_percent');
        });
    }

    public function down(): void
    {
        Schema::table('formula_materials', function (Blueprint $table) {
            $table->dropColumn('use_nesting');
        });
    }
};
