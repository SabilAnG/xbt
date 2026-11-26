<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lokasi toko tempat belanja (mis. cabang atau pasar), tercatat per pembelian. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->string('supplier_location')->nullable()->after('supplier');
        });
    }

    public function down(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropColumn('supplier_location');
        });
    }
};
