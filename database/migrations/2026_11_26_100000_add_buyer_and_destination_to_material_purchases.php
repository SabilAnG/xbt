<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota pembelian mencatat siapa yang belanja, dan tiap baris bisa diarahkan
 * ke gudang tujuan selain gudang asal bahannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->string('buyer')->nullable()->after('supplier_invoice');
        });

        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('material_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropColumn('buyer');
        });
    }
};
