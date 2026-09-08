<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyambung modul produksi ke inventory barang jual.
     *
     * Tanpa kolom ini, knalpot yang selesai dibuat tidak pernah masuk stok
     * `items` — HPP terhitung tapi barangnya tidak bisa dijual lewat menu
     * Penjualan. Nullable, karena formula boleh saja hanya dipakai sebagai
     * kalkulator HPP tanpa menghasilkan barang siap jual.
     */
    public function up(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->foreignId('item_id')
                ->nullable()
                ->after('motorcycle_model_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropColumn('item_id');
        });
    }
};
