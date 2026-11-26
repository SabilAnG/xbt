<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan beli vs satuan pakai untuk bahan per satuan, mis. plat dibeli per
 * lembar tetapi dipakai per cm². `unit` = satuan beli, `use_unit` = satuan
 * pakai (stok & formula), `conversion` = isi satuan pakai per 1 satuan beli.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->string('use_unit', 30)->nullable()->after('unit');
            $table->decimal('conversion', 16, 4)->default(1)->after('use_unit');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['use_unit', 'conversion']);
        });
    }
};
