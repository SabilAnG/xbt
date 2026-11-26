<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pekerjaan yang dijasakan ke vendor beserta tarifnya, supaya HPP perkiraan
 * sudah menghitungnya:
 * - per komponen formula (mis. P1 dibending Rp 3.000/buah, Tabung dichrome);
 * - per formula setengah jadi (seluruh barangnya dikerjakan vendor).
 * Saat produksi dibukukan, jasa komponen jadi biaya + tagihan ke vendor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->string('work_mode', 10)->default('internal')->after('output_material_id');
            $table->string('vendor')->nullable()->after('work_mode');
            $table->string('process', 100)->nullable()->after('vendor');
            $table->decimal('service_cost', 16, 2)->default(0)->after('process');
        });

        Schema::table('formula_components', function (Blueprint $table) {
            $table->string('work_mode', 10)->default('internal')->after('qty');
            $table->string('vendor')->nullable()->after('work_mode');
            $table->string('process', 100)->nullable()->after('vendor');
            // Tarif jasa per buah komponen.
            $table->decimal('service_cost', 16, 2)->default(0)->after('process');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('service_cost', 16, 2)->default(0)->after('other_cost');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            // Tagihan jasa komponen milik produksi ini.
            $table->foreignId('production_id')->nullable()->after('production_plan_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_id');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn('service_cost');
        });

        Schema::table('formula_components', function (Blueprint $table) {
            $table->dropColumn(['work_mode', 'vendor', 'process', 'service_cost']);
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->dropColumn(['work_mode', 'vendor', 'process', 'service_cost']);
        });
    }
};
