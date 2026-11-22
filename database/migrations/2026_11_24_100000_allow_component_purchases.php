<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian bisa berisi komponen jadi (leheran, tutup silencer, …) yang langsung
 * masuk ke gudang setengah jadi, bukan hanya bahan baku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->unsignedBigInteger('material_id')->nullable()->change();
            $table->foreignId('component_id')->nullable()->after('material_id')->constrained()->restrictOnDelete();
        });

        Schema::table('components', function (Blueprint $table) {
            $table->decimal('cost_price', 14, 2)->default(0)->after('min_stock');
        });

        Schema::table('component_movements', function (Blueprint $table) {
            $table->nullableMorphs('source');
        });
    }

    public function down(): void
    {
        Schema::table('component_movements', function (Blueprint $table) {
            $table->dropMorphs('source');
        });

        Schema::table('components', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });

        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('component_id');
        });
    }
};
