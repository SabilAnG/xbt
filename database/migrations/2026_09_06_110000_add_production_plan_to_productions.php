<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak dari rencana ke nota produksinya.
 *
 * Nullable karena nota produksi tetap boleh dibuat sendiri tanpa rencana —
 * pekerjaan dadakan tidak perlu direncanakan dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('production_plan_id')->nullable()->after('formula_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_plan_id');
        });
    }
};
