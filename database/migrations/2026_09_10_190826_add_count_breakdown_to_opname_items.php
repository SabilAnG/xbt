<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara barangnya dihitung, bukan cuma hasilnya.
 *
 * Orang di gudang menghitung "5 batang: 4 utuh, 1 sisa 3 meter" — bukan
 * "27.000 mm". Menyuruhnya mengalikan sendiri itu memindahkan pekerjaan
 * sistem ke tangan orang, dan di situlah salah hitung lahir.
 *
 * Angka mentahnya disimpan supaya nota bisa dibuka lagi persis seperti saat
 * diisi, dan supaya terlihat DARI MANA hasilnya — 3 lembar utuh plus potongan
 * 1200x800 menjelaskan dirinya sendiri, sementara 3.840.000 mm2 tidak.
 *
 * `physical_qty` tetap ada dan tetap dalam satuan pakai: ia yang dipakai
 * pembukuan, dan diturunkan dari ketiga kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_item_opname_items', function (Blueprint $table) {
            // Berapa satuan beli yang utuh: batang, lembar, kg, pcs.
            $table->decimal('count_whole', 16, 3)->nullable()->after('system_qty');

            // Sisanya. Linear dalam meter, berat gram, volume ml, lembaran
            // memakai panjang potongannya dalam mm.
            $table->decimal('count_remainder', 16, 3)->nullable()->after('count_whole');

            // Khusus lembaran: lebar potongan sisa, dalam mm.
            $table->decimal('count_remainder_width', 16, 3)->nullable()->after('count_remainder');
        });
    }

    public function down(): void
    {
        Schema::table('production_item_opname_items', function (Blueprint $table) {
            $table->dropColumn(['count_whole', 'count_remainder', 'count_remainder_width']);
        });
    }
};
