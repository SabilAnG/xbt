<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen knalpot: bagian-bagian yang menyusun satu knalpot.
 *
 * Bertingkat lewat `parent_id` sendiri, bukan dua tabel terpisah. Sekarang
 * kedalamannya dua — Header berisi P1, Silincer berisi Tabung — tapi begitu
 * suatu saat Braket perlu dipecah lagi, tidak perlu migrasi baru.
 *
 * Tiap komponen menunjuk bahan bakunya. BERAPA banyak dan ukuran potongannya
 * bukan urusan di sini: itu berbeda tiap model motor, dan tempatnya nanti di
 * formula. Di sini cukup "P1 dibuat dari pipa Ø28".
 */
return new class extends Migration
{
    /** Susunan awal, mengikuti cara bengkel menyebutnya. */
    private const SUSUNAN = [
        'Header' => ['Pureng', 'Plenger', 'P1', 'P2', 'P3', 'P4', 'Shock Pipa', 'Gantungan Per'],
        'Silincer' => ['Shock', 'Tutup DB', 'Tabung Silincer', 'Braket Atas', 'Braket Tengah', 'Braket Bawah'],
    ];

    public function up(): void
    {
        Schema::create('exhaust_components', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')->nullable()
                ->constrained('exhaust_components')->cascadeOnDelete();

            $table->string('name');
            $table->string('code')->nullable();

            // Bahan bakunya. Boleh kosong untuk bagian induk seperti Header,
            // yang tidak dibuat dari apa pun — ia hanya wadah.
            $table->foreignId('production_item_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        $sekarang = now();
        $urutBagian = 0;

        foreach (self::SUSUNAN as $bagian => $komponen) {
            $urutBagian += 10;

            $indukId = DB::table('exhaust_components')->insertGetId([
                'parent_id' => null,
                'name' => $bagian,
                'sort_order' => $urutBagian,
                'is_active' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);

            $urut = 0;

            foreach ($komponen as $nama) {
                $urut += 10;

                DB::table('exhaust_components')->insert([
                    'parent_id' => $indukId,
                    'name' => $nama,
                    'sort_order' => $urut,
                    'is_active' => true,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exhaust_components');
    }
};
