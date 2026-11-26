<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ukuran dipisah per kolom: pipa = diameter, tebal, panjang batang;
 * plat = panjang & lebar lembar. Kolom spec tetap dipakai sebagai teks
 * ringkas (otomatis untuk pipa & plat, bebas untuk barang lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('shape', 10)->nullable()->after('measure');
            $table->string('diameter', 30)->nullable()->after('spec');
            $table->decimal('thickness_mm', 8, 2)->nullable()->after('diameter');
            $table->decimal('sheet_length_cm', 10, 2)->nullable()->after('thickness_mm');
            $table->decimal('sheet_width_cm', 10, 2)->nullable()->after('sheet_length_cm');
        });

        DB::table('materials')->where('measure', 'length')->update(['shape' => 'pipa']);
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['shape', 'diameter', 'thickness_mm', 'sheet_length_cm', 'sheet_width_cm']);
        });
    }
};
