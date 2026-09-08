<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nota produksi ikut membekukan waktu kerja dan pemakaian mesin.
 *
 * Tanpa ini HPP yang tersimpan di nota tidak akan sama dengan HPP formula,
 * karena upah per jam dan biaya mesin tidak punya tempat untuk dicatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('machine_cost', 16, 2)->default(0)->after('service_cost');
            $table->decimal('total_minutes', 10, 2)->default(0)->after('machine_cost');
        });

        Schema::table('production_costs', function (Blueprint $table) {
            $table->decimal('minutes', 10, 2)->default(0)->after('qty');
            $table->string('rate_type')->default('per_unit')->after('minutes');
        });

        Schema::create('production_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();
            $table->decimal('minutes', 10, 2)->default(0);
            $table->decimal('rate', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_machines');

        Schema::table('production_costs', function (Blueprint $table) {
            $table->dropColumn(['minutes', 'rate_type']);
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn(['machine_cost', 'total_minutes']);
        });
    }
};
