<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga jual boleh ikut diperbarui saat stok opname.
 *
 * Orang yang sedang memegang barang di rak adalah orang yang paling tahu
 * harganya sekarang. Memaksanya menutup hitungan, membuka menu Barang, dan
 * memperbarui harga satu per satu berarti harga itu tidak akan pernah
 * diperbarui.
 *
 * Boleh kosong: baris yang harganya tidak diisi tidak menyentuh harga apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->decimal('unit_price', 16, 2)->nullable()->after('difference');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('unit_price');
        });
    }
};
