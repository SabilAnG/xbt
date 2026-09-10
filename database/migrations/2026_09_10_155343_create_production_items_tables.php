<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master barang produksi — fondasi modul produksi yang dibangun ulang.
 *
 * Satu barang dicatat sekali, lalu tiga sumbu menjelaskannya:
 *
 *   jenis   — Pipa, Plat, Hardware. Untuk mengelompokkan saat mencari.
 *   sumber  — dibeli, dibuat sendiri, atau bisa dua-duanya. Menentukan apakah
 *             kekurangannya jadi daftar belanja atau jadwal kerja.
 *   peran   — bahan utama, aksesoris, atau penolong.
 *
 * Ketiganya sengaja kolom terpisah. Menumpuknya jadi satu akan memaksa
 * kategori seperti "Pipa Aksesoris Beli" yang beranak-pinak tiap kombinasi.
 *
 * Ukuran disimpan apa adanya sesuai bentuknya, dan `shape` yang memberi tahu
 * ukuran mana yang berlaku — bukan ditebak dari kolom mana yang terisi, karena
 * plat strip 30 mm x 6 m punya panjang DAN lebar tapi dipakai per meter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_item_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('production_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');

            $table->foreignId('production_item_category_id')
                ->nullable()->constrained()->nullOnDelete();

            $table->string('source')->default('beli');   // beli | produksi | beli_produksi
            $table->string('role')->default('utama');    // utama | aksesoris | penolong
            $table->string('shape')->default('count');   // linear | sheet | weight | volume | count

            // Satuan beli: batang, lembar, kg, tabung, pcs.
            $table->string('unit')->default('pcs');

            // Ukuran per SATU satuan beli. Yang berlaku ditentukan `shape`.
            $table->decimal('length_mm', 16, 3)->nullable();
            $table->decimal('width_mm', 16, 3)->nullable();
            $table->decimal('diameter_mm', 16, 2)->nullable();
            $table->decimal('thickness_mm', 16, 2)->nullable();
            $table->decimal('weight_gram', 16, 3)->nullable();
            $table->decimal('volume_ml', 16, 3)->nullable();

            // Harga per satuan BELI. Harga per satuan pakai diturunkan.
            $table->decimal('cost_price', 16, 2)->default(0);

            // Stok disimpan dalam satuan PAKAI supaya sisa potongan bisa
            // dinyatakan — "sisa 41,8 m" lebih berguna daripada "6,97 batang".
            $table->decimal('stock', 16, 3)->default(0);
            $table->decimal('min_stock', 16, 3)->default(0);

            // Sisa potong di bawah angka ini dihitung sampah, bukan stok.
            $table->decimal('min_reusable', 16, 3)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['source', 'role']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_items');
        Schema::dropIfExists('production_item_categories');
    }
};
