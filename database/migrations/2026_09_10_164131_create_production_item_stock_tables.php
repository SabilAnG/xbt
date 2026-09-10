<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kartu stok barang produksi, dan stok opname yang menulis ke sana.
 *
 * Aturan yang membuat angkanya bisa dipercaya: `production_items.stock` tidak
 * pernah diketik. Ia selalu turunan dari kartu ini, jadi setiap perubahan punya
 * baris yang menjelaskan asalnya — siapa, kapan, karena dokumen apa.
 *
 * Stok opname adalah jalan masuk stok pertama. Nota berstatus draft tidak
 * menyentuh apa pun; angka baru nyata setelah dibukukan, dan pembatalannya
 * menghapus jejak lalu menghitung ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_item_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_item_id')->constrained()->cascadeOnDelete();

            // opname | purchase | production | adjustment
            $table->string('type');

            // Dalam satuan PAKAI, sama seperti kolom stok.
            $table->decimal('qty_in', 16, 3)->default(0);
            $table->decimal('qty_out', 16, 3)->default(0);
            $table->decimal('balance_after', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 4)->default(0);

            // Dokumen asalnya. Morph supaya pembelian dan produksi nanti bisa
            // menulis ke kartu yang sama tanpa mengubah struktur.
            $table->nullableMorphs('source');

            $table->date('moved_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['production_item_id', 'moved_at']);
        });

        Schema::create('production_item_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date');
            $table->string('counted_by')->nullable();
            $table->string('status')->default('draft');   // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_item_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_item_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_item_id')->constrained()->restrictOnDelete();

            // Stok sistem dibekukan saat barang dipilih, supaya selisihnya tetap
            // bercerita walau stok bergerak sebelum notanya dibukukan.
            $table->decimal('system_qty', 16, 3)->default(0);
            $table->decimal('physical_qty', 16, 3)->default(0);
            $table->decimal('difference', 16, 3)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['production_item_opname_id', 'production_item_id'], 'opname_item_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_item_opname_items');
        Schema::dropIfExists('production_item_opnames');
        Schema::dropIfExists('production_item_movements');
    }
};
