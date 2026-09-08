<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tingkatan harga jual: reseller, toko, retail, marketplace.
 *
 * `fee_percent` menampung potongan marketplace supaya harga yang dipasang di
 * sana sudah menutup biaya admin — bukan margin yang diam-diam terpotong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('margin_percent', 6, 2)->default(0);
            $table->decimal('fee_percent', 6, 2)->default(0);
            $table->string('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tiers');
    }
};
