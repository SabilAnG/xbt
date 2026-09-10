<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peran dan sumber pindah naik ke jenis barang.
 *
 * Seluruh pipa adalah bahan utama yang dibeli; seluruh baut adalah aksesoris
 * yang dibeli. Menjawabnya sekali per jenis jauh lebih masuk akal daripada
 * mengulanginya tiap kali menambah barang.
 *
 * Barang tetap menyimpan kolomnya sendiri: nilainya terisi otomatis dari jenis
 * saat dipilih, tapi masih bisa diubah untuk kasus yang menyimpang — satu jenis
 * jarang seragam seratus persen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_item_categories', function (Blueprint $table) {
            $table->string('source')->default('beli')->after('slug');
            $table->string('role')->default('utama')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('production_item_categories', function (Blueprint $table) {
            $table->dropColumn(['source', 'role']);
        });
    }
};
