<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seperti di EMS: barang bisa dibeli, bisa diproduksi, atau keduanya
 * (mis. tabung silincer). Untuk yang keduanya, rencana produksi memecah
 * kekurangannya: sebagian dibuat, sebagian dibeli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->boolean('is_purchasable')->default(true)->after('notes');
            $table->boolean('is_producible')->default(true)->after('is_purchasable');
            // Saran default di rencana produksi bila bisa keduanya: buat | beli.
            $table->string('preferred_source', 10)->default('beli')->after('is_producible');
        });

        // Barang yang sudah jadi hasil formula tetap disarankan dibuat, seperti sebelumnya.
        DB::table('materials')->whereIn('id', DB::table('formulas')->whereNotNull('output_material_id')->pluck('output_material_id'))
            ->update(['preferred_source' => 'buat']);

        Schema::create('production_plan_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            // Bagian kekurangan yang dibeli (satuan pakai); sisanya dibuat.
            $table->decimal('buy_qty', 16, 3)->default(0);
            $table->timestamps();
            $table->unique(['production_plan_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_plan_sources');

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['is_purchasable', 'is_producible', 'preferred_source']);
        });
    }
};
