<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang modul produksi lama, yang akan dibangun ulang mengikuti skema baru.
 *
 * Nota produksi yang sudah dibukukan pernah menambah stok barang jual dan
 * menyentuh kas. Menghapus tabelnya begitu saja meninggalkan saldo kas dan stok
 * jual yang salah tanpa jejak asalnya — jadi jejak itu dihapus lebih dulu, lalu
 * stok dan saldo dihitung ulang dari baris yang tersisa. Persis yang dilakukan
 * tombol "Batalkan", hanya sekaligus.
 *
 * Tidak ada `down()` yang jujur: struktur lama bisa dibuat lagi dari riwayat
 * git, tapi isinya tidak bisa dikembalikan. Backup sebelum menjalankan ini.
 */
return new class extends Migration
{
    /** Urutan penting: anak dulu, induk belakangan. */
    private const TABLES = [
        'formula_costs', 'formula_machines', 'formula_materials',
        'production_costs', 'production_machines', 'production_materials',
        'production_plan_lines', 'productions', 'production_plans',
        'material_opname_items', 'material_opnames',
        'material_purchase_items', 'material_purchases',
        'material_movements', 'material_stocks',
        'formulas', 'materials', 'material_categories',
        'racks', 'warehouses',
        'cost_components', 'machines', 'overhead_items', 'vendors',
    ];

    private const SOURCES = [
        'App\Models\Production',
        'App\Models\MaterialPurchase',
        'App\Models\MaterialOpname',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('stock_movements')) {
                DB::table('stock_movements')->whereIn('source_type', self::SOURCES)->delete();
                $this->recalculateItemStock();
            }

            if (Schema::hasTable('wallet_transactions')) {
                DB::table('wallet_transactions')->whereIn('source_type', self::SOURCES)->delete();
                $this->recalculateWalletBalance();
            }
        });

        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Sengaja kosong. Lihat catatan di atas.
    }

    /** Stok barang jual selalu turunan kartu stok, bukan angka berdiri sendiri. */
    private function recalculateItemStock(): void
    {
        DB::table('items')->update([
            'stock' => DB::raw('(
                SELECT COALESCE(SUM(qty_in) - SUM(qty_out), 0)
                FROM stock_movements WHERE stock_movements.item_id = items.id
            )'),
        ]);
    }

    private function recalculateWalletBalance(): void
    {
        DB::table('wallets')->update([
            'current_balance' => DB::raw('(
                opening_balance
                + COALESCE((SELECT SUM(amount) FROM wallet_transactions
                    WHERE wallet_transactions.wallet_id = wallets.id AND direction = "in"), 0)
                - COALESCE((SELECT SUM(amount) FROM wallet_transactions
                    WHERE wallet_transactions.wallet_id = wallets.id AND direction = "out"), 0)
            )'),
        ]);
    }
};
