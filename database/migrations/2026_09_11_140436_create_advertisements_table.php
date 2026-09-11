<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Iklan yang dipasang orang luar di situs Hypersonic.
 *
 * Fotonya dikirim pemasang, posisinya ditentukan admin, dan tautannya membawa
 * pengunjung ke situs si pemasang. Yang menentukan tampil atau tidak bukan
 * hanya tombol aktif: masa tayang habis juga menurunkannya sendiri, supaya
 * iklan yang sudah lewat tidak perlu diingat-ingat untuk dimatikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title');

            // Di mana banner ini muncul di halaman.
            $table->string('position')->default('footer');

            $table->string('image_path');
            $table->string('target_url');

            // Nama dan kontak pemasang — untuk ditagih dan dihubungi, tidak
            // pernah ditampilkan ke pengunjung.
            $table->string('advertiser_name')->nullable();
            $table->string('advertiser_contact')->nullable();

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // Berapa kali banner ini diklik. Ditambah lewat route redirect,
            // bukan dari JavaScript yang bisa dimatikan pemblokir iklan.
            $table->unsignedBigInteger('clicks')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['position', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
