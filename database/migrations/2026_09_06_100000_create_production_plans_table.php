<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana produksi: "bulan depan mau bikin 20 Mio, 15 Beat".
 *
 * Bukan dokumen buku besar — tidak menyentuh stok sama sekali. Gunanya
 * menjawab dua pertanyaan sebelum belanja: bahan apa yang kurang, dan berapa
 * satuan beli yang harus ditebus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number')->unique();
            $table->date('planned_for');
            $table->string('title')->nullable();
            $table->foreignId('material_purchase_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formula_id')->constrained()->restrictOnDelete();
            $table->decimal('target_qty', 16, 3)->default(0);
            $table->string('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_lines');
        Schema::dropIfExists('production_plans');
    }
};
