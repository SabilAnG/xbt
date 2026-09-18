<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bahan bakunya apa — stainless, besi, galvanis.
 *
 * Kolom sendiri, bukan diselipkan ke nama barang. "Pipa SS 201 Ø28" memang
 * menyebutkannya, tapi nama adalah teks bebas: begitu ada yang menulis "Pipa
 * Stainless 28" daftarnya tidak bisa lagi disaring per bahan, dan pertanyaan
 * yang paling sering datang justru itu — berapa nilai stok stainless, mana saja
 * yang masih besi.
 *
 * Berlaku untuk seluruh bentuk, bukan hanya pipa. Baut stainless dan baut besi
 * berbeda harga dan berbeda peruntukan, dan keduanya barang satuan.
 *
 * Dibiarkan nullable: barang yang sudah telanjur ada tidak punya jawabannya,
 * dan menebak bahannya lebih buruk daripada mengakui belum tahu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_items', function (Blueprint $table) {
            $table->string('material')->nullable()->after('shape');
            $table->index('material');
        });
    }

    public function down(): void
    {
        Schema::table('production_items', function (Blueprint $table) {
            $table->dropIndex(['material']);
            $table->dropColumn('material');
        });
    }
};
