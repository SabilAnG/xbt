<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biaya jasa yang dibebankan ke sebuah order produksi.
     * Tarif dibekukan saat pembukuan agar HPP historis tetap konsisten.
     */
    public function up(): void
    {
        Schema::create('production_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_component_id')->constrained()->restrictOnDelete();

            $table->decimal('qty', 16, 3)->default(1);
            $table->decimal('rate', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['production_id', 'cost_component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_costs');
    }
};
