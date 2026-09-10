<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua sumbu yang selama ini tidak terekam di master bahan.
 *
 * `source` — didapat dari mana: dibeli, dibuat sendiri, atau dua-duanya.
 *   Cone dan perforated core bisa ditebus di toko maupun dibuat di bengkel,
 *   dan itu menentukan apakah kekurangannya jadi daftar belanja atau jadwal
 *   kerja.
 *
 * `role` — perannya di produk: bahan utama, aksesoris, atau penolong.
 *   Kategori yang sudah ada memuat JENIS bahan (Pipa, Plat, Hardware); ini
 *   sumbu yang berbeda, dan menumpuk keduanya di satu kolom akan memaksa
 *   kategori seperti "Pipa Aksesoris" yang beranak-pinak.
 *
 * Bawaannya dibeli + bahan utama: itu keadaan seluruh 19 bahan yang ada
 * sekarang, jadi tidak ada data lama yang berubah arti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('source')->default('beli')->after('material_category_id');
            $table->string('role')->default('utama')->after('source');

            $table->index(['source', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropIndex(['source', 'role']);
            $table->dropColumn(['source', 'role']);
        });
    }
};
