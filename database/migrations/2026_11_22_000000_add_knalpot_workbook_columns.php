<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom yang dipakai buku kerja "Manajemen Produksi & Penjualan Knalpot".
 *
 * Buku kerja itu menyusun produk dari Model Motor + Finishing + Tipe + Ukuran +
 * Merek, memakai satu resep BOM untuk banyak produk (baris resep bisa khusus
 * merek FD/XBT), dan mencatat saluran jual, uang masuk, serta status data
 * pengeluaran. Semuanya bersifat tambahan; tidak ada kolom lama yang diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('model_motor')->nullable()->after('name');
            $table->string('finishing')->nullable()->after('model_motor');
            $table->string('variant')->nullable()->after('finishing');
            $table->string('size')->nullable()->after('variant');
            $table->string('brand')->nullable()->after('size');
            $table->decimal('sell_price_online', 16, 2)->default(0)->after('sell_price');
            $table->foreignId('formula_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('formula_materials', function (Blueprint $table) {
            // null = berlaku untuk semua merek.
            $table->string('brand')->nullable()->after('material_id');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('formula_id')->constrained()->nullOnDelete();
            $table->string('worker')->nullable()->after('item_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('channel')->nullable()->after('customer_phone');
            $table->decimal('paid_amount', 16, 2)->default(0)->after('total_cost');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->boolean('recorded_in_expenses')->default(false)->after('total');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('from_account')->nullable()->after('paid_to');
            $table->text('notes')->nullable()->after('from_account');
            // null | ESTIMASI | PERLU DIRINCI
            $table->string('data_status')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['from_account', 'notes', 'data_status']);
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropColumn('recorded_in_expenses');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['channel', 'paid_amount']);
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('worker');
        });

        Schema::table('formula_materials', function (Blueprint $table) {
            $table->dropColumn('brand');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formula_id');
            $table->dropColumn(['model_motor', 'finishing', 'variant', 'size', 'brand', 'sell_price_online']);
        });
    }
};
