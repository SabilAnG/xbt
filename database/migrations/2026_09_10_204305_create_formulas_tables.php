<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formula: resep satu knalpot untuk satu type motor.
 *
 * Barisnya mengikuti daftar komponen — Pureng, P1, Tabung Silincer — dan di
 * tiap baris barulah dijawab dua hal yang memang berbeda tiap motor:
 * bahannya apa, dan berapa ukurannya. Karena itu formula yang memegang
 * jawaban ini, bukan daftar komponennya.
 *
 * Ukuran diisi apa adanya sesuai bentuk bahannya — pipa cukup panjangnya,
 * plat panjang kali lebar, baut cukup jumlahnya — lalu `qty` dalam satuan
 * pakai diturunkan otomatis. Tidak ada perkalian yang perlu dikerjakan orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');

            // Type motornya, diambil dari master motor yang sudah ada.
            $table->foreignId('motorcycle_model_id')->nullable()
                ->constrained()->nullOnDelete();

            // Satu kali resep menghasilkan berapa, dan disebut apa.
            $table->decimal('output_qty', 16, 3)->default(1);
            $table->string('output_unit')->default('set');

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('formula_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();

            $table->foreignId('exhaust_component_id')->constrained()->restrictOnDelete();

            // Boleh kosong dulu: barisnya bisa didaftar sebelum bahannya
            // diputuskan, dan yang belum terisi justru perlu terlihat.
            $table->foreignId('production_item_id')->nullable()
                ->constrained()->nullOnDelete();

            // length | rect | count — cara ukurannya disebut.
            $table->string('input_mode')->default('count');

            // Satuan yang dipakai MENGETIK ukuran potongan. Bengkel menyebut
            // potongan pipa dalam cm, jadi itu bawaannya.
            $table->string('size_unit')->default('cm');

            $table->decimal('piece_length_mm', 16, 3)->nullable();
            $table->decimal('piece_width_mm', 16, 3)->nullable();
            $table->decimal('piece_count', 16, 3)->default(1);

            // Kebutuhan dalam satuan pakai, diturunkan dari ketiga kolom di atas.
            $table->decimal('qty', 16, 3)->default(0);

            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Satu komponen sekali saja dalam satu formula; kalau butuh dua
            // potongan berbeda, itu dua komponen.
            $table->unique(['formula_id', 'exhaust_component_id'], 'formula_komponen_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_lines');
        Schema::dropIfExists('formulas');
    }
};
