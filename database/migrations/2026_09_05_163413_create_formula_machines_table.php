<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemakaian mesin di dalam sebuah formula, dalam menit.
     */
    public function up(): void
    {
        Schema::create('formula_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained()->restrictOnDelete();

            $table->string('bom_group')->default('finishing');
            $table->decimal('minutes', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['formula_id', 'machine_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_machines');
    }
};
