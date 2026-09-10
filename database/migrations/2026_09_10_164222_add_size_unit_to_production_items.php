<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan yang dipakai saat MENGETIK ukuran.
 *
 * Pipa disebut orang bengkel dalam inch, plat dalam mm, panjang batang dalam
 * meter. Memaksa semuanya diketik dalam mm mengundang salah nol.
 *
 * Nilainya sendiri tetap disimpan dalam mm. Kolom ini hanya mengatur cara
 * mengetik dan menampilkan kembali, sehingga seluruh perhitungan — konversi
 * satuan, nesting, HPP — tidak perlu tahu apa-apa soal ini dan tidak bisa
 * ikut salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_items', function (Blueprint $table) {
            $table->string('size_unit')->default('mm')->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('production_items', function (Blueprint $table) {
            $table->dropColumn('size_unit');
        });
    }
};
