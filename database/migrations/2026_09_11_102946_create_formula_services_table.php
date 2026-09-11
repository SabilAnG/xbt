<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jasa yang dipakai sebuah formula — biaya lain-lain di luar bahan.
 *
 * Yang disimpan cuma jasa apa dan berapa banyak. Tarifnya tetap dibaca dari
 * master jasa saat dipanggil, tidak disalin ke sini: begitu ongkos chrome naik
 * di satu tempat, seluruh resep yang memakainya ikut menyesuaikan. Sama seperti
 * harga bahan di `formula_lines`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formula_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();

            // Dilarang menghapus jasa yang masih dipakai resep — biaya yang
            // hilang diam-diam berakhir di harga jual yang keliru.
            $table->foreignId('production_service_id')->constrained()->restrictOnDelete();

            // Berapa satuan jasa itu dipakai: 1 unit chrome, 12 titik las.
            $table->decimal('qty', 16, 3)->default(1);

            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Satu jasa sekali saja dalam satu formula; kalau perlu dua tarif
            // berbeda, itu dua jasa di masternya.
            $table->unique(['formula_id', 'production_service_id'], 'formula_jasa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_services');
    }
};
