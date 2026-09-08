<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proses kerja bisa ditagih per jam, bukan hanya per unit.
     *
     * Sebelumnya "Potong Pipa" harus diisi tarif jadi per unit — padahal yang
     * Anda tahu adalah "10 menit" dan "upah Rp25.000/jam". Sekarang komponen
     * biaya menyimpan tarif per jam, dan formula mengisi menitnya.
     */
    public function up(): void
    {
        Schema::table('cost_components', function (Blueprint $table) {
            // per_hour | per_unit
            $table->string('rate_type')->default('per_unit')->after('type');
            $table->decimal('default_minutes', 10, 2)->nullable()->after('rate');
        });

        Schema::table('formula_costs', function (Blueprint $table) {
            // Dipakai bila komponennya bertarif per jam.
            $table->decimal('minutes', 10, 2)->nullable()->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('cost_components', function (Blueprint $table) {
            $table->dropColumn(['rate_type', 'default_minutes']);
        });

        Schema::table('formula_costs', function (Blueprint $table) {
            $table->dropColumn('minutes');
        });
    }
};
