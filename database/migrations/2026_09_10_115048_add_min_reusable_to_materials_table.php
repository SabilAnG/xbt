<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas sisa yang masih layak dipakai lagi.
 *
 * Memotong 7 potong 800 mm dari batang 6 m menyisakan 382 mm. Apakah itu stok
 * atau sampah bukan urusan sistem — itu keputusan bengkel, dan berbeda tiap
 * bahan. Angka ini yang memisahkan keduanya, dalam satuan DASAR bahan
 * (mm, mm², gram, ml, pcs).
 *
 * Nol berarti semua sisa dianggap masih terpakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('min_reusable', 16, 3)->default(0)->after('min_stock');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('min_reusable');
        });
    }
};
