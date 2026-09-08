<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overhead tidak lagi persentase per formula.
 *
 * Persentase membuat knalpot berbahan mahal seolah menanggung sewa bengkel
 * lebih besar, padahal waktu pakai bengkelnya sama. Sekarang overhead diambil
 * dari total biaya tetap bulanan dibagi target produksi — satu angka untuk
 * semua formula, dikelola di menu Overhead Bulanan.
 *
 * Nota produksi lama tidak terpengaruh: yang disimpan di sana adalah nilai
 * rupiah overhead-nya, bukan persentasenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->dropColumn('overhead_percent');
        });
    }

    public function down(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->decimal('overhead_percent', 8, 2)->default(0)->after('output_unit');
        });
    }
};
