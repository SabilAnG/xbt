<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen tidak lagi menunjuk bahan bakunya.
 *
 * Daftar komponen adalah master: apa saja bagian penyusun knalpot. Bahan apa
 * yang dipakai, berapa banyak, dan berukuran berapa itu berbeda tiap model
 * motor — semuanya milik formula. Menaruh salah satunya di sini memaksa
 * daftar komponen digandakan per model, atau menyimpan jawaban yang cuma
 * benar untuk satu model.
 *
 * Kolomnya masih kosong seluruhnya saat ini, jadi tidak ada yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exhaust_components', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('exhaust_components', function (Blueprint $table) {
            $table->foreignId('production_item_id')->nullable()->after('code')
                ->constrained()->nullOnDelete();
        });
    }
};
