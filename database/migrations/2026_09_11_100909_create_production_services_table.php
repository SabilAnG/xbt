<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jasa produksi — biaya membuat knalpot yang bukan bahan.
 *
 * Chrome, poles, las argon, bending: pekerjaannya berbeda-beda tapi sama-sama
 * ditagih per satuan. `rate` adalah tarif untuk satu `unit` — satu unit chrome,
 * satu titik las, satu set poles — dan formula nanti tinggal menyebut berapa
 * banyak satuan yang dipakai.
 *
 * Modul lama menyimpan tarif per jam juga. Itu tidak dibawa ke sini: yang
 * dipakai bengkel saat menyebut harga selalu "sekian per unit", dan pilihan
 * kedua yang tidak pernah dipakai hanya menambah satu keputusan di tiap baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Satuan tarif: unit, titik, set, pcs, batang.
            $table->string('unit')->default('unit');
            $table->decimal('rate', 16, 2)->default(0);

            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_services');
    }
};
