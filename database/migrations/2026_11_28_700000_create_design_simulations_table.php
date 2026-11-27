<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulasi desain knalpot: ukuran leheran & silincer (parametrik), foto
 * referensi, dan gambar pratinjau 3D. Estimasi tenaga dihitung ulang dari
 * parameter (App\Support\ExhaustSimulator), jadi tidak disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_simulations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('motor', 40);
            $table->json('params');
            $table->json('references')->nullable();
            $table->string('thumbnail')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_simulations');
    }
};
