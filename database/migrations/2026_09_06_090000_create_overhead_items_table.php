<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya tetap bulanan bengkel: sewa, listrik penerangan, internet, gaji admin.
 *
 * Dipisah per pos supaya kalau sewa naik cukup satu baris yang diubah, dan
 * pengguna bisa melihat pos mana yang paling membebani HPP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overhead_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('monthly_cost', 16, 2)->default(0);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overhead_items');
    }
};
