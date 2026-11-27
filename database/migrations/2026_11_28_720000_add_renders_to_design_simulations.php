<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Hasil render foto-realistis AI (motor + knalpot terpasang) per desain. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_simulations', function (Blueprint $table) {
            $table->json('renders')->nullable()->after('motor_photos');
        });
    }

    public function down(): void
    {
        Schema::table('design_simulations', function (Blueprint $table) {
            $table->dropColumn('renders');
        });
    }
};
