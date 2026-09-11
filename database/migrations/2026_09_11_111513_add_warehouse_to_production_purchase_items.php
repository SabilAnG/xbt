<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang tujuan pindah ke tiap baris.
 *
 * Satu nota toko bisa memuat pipa yang masuk gudang bahan mentah dan baut yang
 * masuk gudang lain. Memaksa satu gudang untuk seluruh nota membuat orang
 * memecah notanya jadi dua — dan nota yang dipecah tidak lagi cocok dengan
 * kertas aslinya saat dicari belakangan.
 *
 * Kolom di notanya tetap ada, tapi turun derajat jadi gudang bawaan: yang
 * mengisi baris baru, bukan yang menentukan ke mana barangnya masuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_purchase_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('production_item_id')
                ->constrained()->restrictOnDelete();
        });

        // Baris yang sudah ada mewarisi gudang notanya.
        DB::statement('
            UPDATE production_purchase_items
            SET warehouse_id = (
                SELECT warehouse_id FROM production_purchases
                WHERE production_purchases.id = production_purchase_items.production_purchase_id
            )
            WHERE warehouse_id IS NULL
        ');

        Schema::table('production_purchases', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
