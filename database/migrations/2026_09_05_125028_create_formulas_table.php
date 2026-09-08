<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formula / resep produksi (BOM) — mis. "Knalpot Mio 1 set".
     *
     * Menyimpan berapa bahan dan jasa yang dibutuhkan untuk menghasilkan
     * `output_qty` unit. HPP per unit dihitung dari isinya, jadi begitu harga
     * pipa naik, HPP ikut menyesuaikan tanpa perlu diketik ulang.
     */
    public function up(): void
    {
        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');

            // Tautan opsional ke type motor yang sudah ada di master data.
            $table->foreignId('motorcycle_model_id')->nullable()->constrained()->nullOnDelete();

            // Berapa unit yang dihasilkan satu kali resep dijalankan.
            $table->decimal('output_qty', 16, 3)->default(1);
            $table->string('output_unit')->default('set');

            // Overhead sebagai persen dari (bahan + jasa), untuk listrik, gas,
            // dan biaya tak langsung lain yang tidak dirinci per komponen.
            $table->decimal('overhead_percent', 8, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formulas');
    }
};
