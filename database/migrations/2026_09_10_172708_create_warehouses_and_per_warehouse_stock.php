<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang, dan stok yang kini dihitung per gudang.
 *
 * Sebelumnya `production_items.stock` satu angka, dan itu tidak lagi cukup:
 * 40 m pipa di gudang mana? Sekarang rinciannya ada di
 * `production_item_stocks`, sementara kolom stok di barang tetap ada sebagai
 * TOTAL seluruh gudang — supaya "stok menipis" tetap sederhana.
 *
 * Aturannya tidak berubah: stok tidak pernah diketik, selalu turunan kartu
 * stok. Yang bertambah hanya satu pertanyaan di tiap barisnya — di gudang mana.
 */
return new class extends Migration
{
    /** Empat gudang yang memang dipakai bengkel, langsung tersedia. */
    private const GUDANG = [
        ['BM', 'Gudang Bahan Mentah', 'bahan_mentah', 'Pipa, plat, baut, kawat las — apa pun yang dibeli mentah.', 10],
        ['BSJ', 'Gudang Barang Setengah Jadi', 'setengah_jadi', 'Cone dan perforated core buatan sendiri, menunggu dirakit.', 20],
        ['FG', 'Gudang Finish Good', 'finish_good', 'Knalpot jadi, siap dijual.', 30],
        ['BS', 'Gudang Bahan Sisa', 'bahan_sisa', 'Sisa potong yang masih di atas batas pakai — dipakai lagi sebelum memotong batang baru.', 40],
    ];

    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type');   // bahan_mentah | setengah_jadi | finish_good | bahan_sisa
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $sekarang = now();

        DB::table('warehouses')->insert(array_map(
            fn (array $g) => [
                'code' => $g[0], 'name' => $g[1], 'type' => $g[2],
                'description' => $g[3], 'sort_order' => $g[4],
                'is_active' => true, 'created_at' => $sekarang, 'updated_at' => $sekarang,
            ],
            self::GUDANG,
        ));

        // Rincian stok per gudang. Kolom stok di barang jadi jumlah baris ini.
        Schema::create('production_item_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 16, 3)->default(0);
            $table->timestamps();

            $table->unique(['production_item_id', 'warehouse_id'], 'stok_barang_gudang_unik');
        });

        // Nullable karena baris lama belum bergudang; ke depan selalu diisi.
        Schema::table('production_item_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('production_item_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('production_item_opnames', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('opname_date')
                ->constrained()->nullOnDelete();
        });

        // Gudang bawaan dijawab sekali per jenis, sama seperti peran dan sumber.
        Schema::table('production_item_categories', function (Blueprint $table) {
            $table->foreignId('default_warehouse_id')->nullable()->after('role')
                ->constrained('warehouses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_item_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_warehouse_id');
        });

        Schema::table('production_item_opnames', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('production_item_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::dropIfExists('production_item_stocks');
        Schema::dropIfExists('warehouses');
    }
};
