<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Foto motor untuk simulasi "Di motor" (knalpot 3D ditempel di foto). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_simulations', function (Blueprint $table) {
            $table->json('motor_photos')->nullable()->after('references');
        });
    }

    public function down(): void
    {
        Schema::table('design_simulations', function (Blueprint $table) {
            $table->dropColumn('motor_photos');
        });
    }
};
