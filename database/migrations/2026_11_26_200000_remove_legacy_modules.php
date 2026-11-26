<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Benar-benar mulai dari nol (2026-11-26).
 *
 * Dibuang: Master HPP (komponen biaya, mesin, overhead, tingkatan harga, type
 * & merek motor), Host Live & Tukang (jam live, absen, gaji), Daftar Pilihan,
 * dan arsip Riwayat Pengadaan. Penjualan, Pengeluaran, Dompet, dan Merek tetap
 * ada tetapi isinya dikosongkan. Website tidak disentuh, kecuali pengaturan
 * produksi lama selain lebar potong gergaji.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const DROPPED = [
        'cost_components', 'machines', 'overhead_items', 'price_tiers',
        'host_payrolls', 'live_sessions', 'hosts',
        'worker_payrolls', 'attendances', 'attendance_days', 'workers',
        'list_options', 'procurement_histories',
        'item_motorcycle_model', 'motorcycle_models', 'motorcycle_brands',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('formula_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cost_component_id');
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('motorcycle_model_id');
        });

        foreach (self::DROPPED as $table) {
            Schema::dropIfExists($table);
        }

        DB::table('wallet_transactions')->delete();
        DB::table('wallet_transfers')->delete();
        DB::table('wallets')->delete();
        DB::table('expenses')->delete();
        DB::table('expense_categories')->delete();
        DB::table('expense_types')->delete();
        DB::table('brands')->delete();
        DB::table('document_counters')->where('document', 'expenses')->delete();
        DB::table('settings')->where('key', 'like', 'produksi.%')->where('key', '!=', 'produksi.kerf_mm')->delete();

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Tidak dibalik: pulihkan dari cadangan database.
    }
};
