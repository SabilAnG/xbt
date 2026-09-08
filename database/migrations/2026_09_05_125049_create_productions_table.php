<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order produksi: menjalankan sebuah formula sebanyak N kali.
     *
     * Saat dibukukan, stok bahan berkurang sesuai formula dan seluruh biaya
     * dibekukan ke nota ini — sehingga HPP historis tidak ikut berubah ketika
     * harga pipa naik bulan depan.
     */
    public function up(): void
    {
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_number')->unique();
            $table->date('produced_at');
            $table->foreignId('formula_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();

            // Berapa kali resep dijalankan, dan total unit yang dihasilkan.
            $table->decimal('batch_qty', 16, 3)->default(1);
            $table->decimal('output_qty', 16, 3)->default(0);

            // Biaya dibekukan saat pembukuan.
            $table->decimal('material_cost', 16, 2)->default(0);
            $table->decimal('service_cost', 16, 2)->default(0);
            $table->decimal('overhead_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->decimal('hpp_per_unit', 16, 2)->default(0);

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'produced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
