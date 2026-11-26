<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventori dibangun ulang dari nol.
 *
 * Alur barunya: Formula (bagian → komponen → bahan) → Rencana Produksi →
 * Pembelian bahan → Gudang → Produksi (potong pipa, sisa ke Gudang Stok Sisa)
 * → Finish Good → Penjualan → Keuangan.
 *
 * Semua tabel inventori lama dibuang beserta datanya, termasuk stok produk
 * jadi dan penjualan yang memakainya. Master HPP (komponen biaya, mesin,
 * overhead, tingkatan harga), dompet, pengeluaran, gaji, dan website tetap.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const OLD_TABLES = [
        'component_movements', 'component_materials', 'components',
        'formula_machines', 'formula_costs', 'formula_materials',
        'production_machines', 'production_costs', 'production_materials',
        'productions', 'production_plan_lines', 'production_plans',
        'material_opname_items', 'material_opnames',
        'material_purchase_items', 'material_purchases',
        'material_movements', 'material_stocks', 'racks',
        'materials', 'material_categories', 'formulas',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasColumn('items', 'formula_id')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('formula_id');
            });
        }

        foreach (self::OLD_TABLES as $table) {
            Schema::dropIfExists($table);
        }

        // Stok produk jadi dan penjualannya ikut dikosongkan.
        $soldClasses = ['App\\Models\\Sale', 'App\\Models\\MaterialPurchase', 'App\\Models\\Production'];
        DB::table('wallet_transactions')->whereIn('source_type', $soldClasses)->delete();
        DB::table('sale_items')->delete();
        DB::table('sales')->delete();
        DB::table('stock_opname_items')->delete();
        DB::table('stock_opnames')->delete();
        DB::table('stock_movements')->delete();
        DB::table('item_motorcycle_model')->delete();
        DB::table('products')->update(['item_id' => null]);
        DB::table('items')->delete();
        DB::table('document_counters')->whereIn('document', ['sales', 'material_purchases', 'productions', 'production_plans', 'material_opnames'])->delete();

        Schema::enableForeignKeyConstraints();

        if (! DB::table('warehouses')->where('type', 'sisa_produksi')->exists()) {
            DB::table('warehouses')->insert([
                'name' => 'Gudang Stok Sisa Produksi', 'code' => 'GD-SISA', 'type' => 'sisa_produksi',
                'description' => 'Potongan sisa pipa hasil produksi yang masih bisa dipakai lagi.',
                'is_active' => true, 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('warehouses')->where('type', 'finish_good')->update(['sort_order' => 4]);
        }

        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();
            // Gudang asal: bahan baku (dibeli mentah) atau setengah jadi (dibeli jadi).
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            // unit = dihitung per satuan; length = pipa, dihitung per batang & cm.
            $table->string('measure', 10)->default('unit');
            $table->string('unit', 30)->default('Pcs');
            $table->string('spec')->nullable();
            $table->decimal('bar_length_cm', 10, 2)->nullable();
            $table->decimal('min_reusable_cm', 10, 2)->default(0);
            // Harga rata-rata per satuan stok (per cm untuk pipa).
            $table->decimal('avg_cost', 16, 4)->default(0);
            // Harga beli terakhir per satuan beli (per batang untuk pipa).
            $table->decimal('last_price', 16, 2)->default(0);
            // Cache stok total: satuan, atau cm untuk pipa.
            $table->decimal('stock', 16, 3)->default(0);
            $table->decimal('min_stock', 16, 3)->default(0);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['warehouse_id', 'name']);
        });

        Schema::create('material_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 16, 3)->default(0);
            $table->decimal('value', 18, 2)->default(0);
            $table->timestamps();
            $table->unique(['material_id', 'warehouse_id']);
        });

        Schema::create('pipe_pieces', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('initial_length_cm', 10, 2);
            $table->decimal('length_cm', 10, 2);
            $table->decimal('cost_per_cm', 16, 4)->default(0);
            // tersedia | habis | afkir
            $table->string('status', 10)->default('tersedia');
            $table->nullableMorphs('source');
            $table->timestamps();
            $table->index(['material_id', 'status', 'warehouse_id']);
        });

        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipe_piece_id')->nullable()->constrained()->nullOnDelete();
            // pembelian | pemakaian | sisa | afkir | penyesuaian
            $table->string('type', 20);
            $table->decimal('qty_in', 16, 3)->default(0);
            $table->decimal('qty_out', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->decimal('balance_after', 16, 3)->default(0);
            $table->nullableMorphs('source');
            $table->dateTime('moved_at');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['material_id', 'warehouse_id', 'moved_at']);
        });

        Schema::create('formulas', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // matic | bebek | sport | lainnya
            $table->string('motor_category', 20)->default('matic');
            $table->foreignId('motorcycle_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('motor_type')->nullable();
            $table->string('variant')->nullable();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('formula_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('formula_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_part_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Berapa buah komponen ini di 1 knalpot.
            $table->decimal('qty', 10, 3)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('formula_component_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_component_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            // Jumlah untuk 1 komponen: satuan bahan, atau panjang potong (cm) untuk pipa.
            $table->decimal('qty', 12, 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('formula_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 16, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('production_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number')->unique();
            $table->date('planned_for');
            $table->string('title')->nullable();
            // draft | proses | selesai | batal
            $table->string('status', 10)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('production_plan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formula_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 12, 3);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('purchased_at');
            $table->string('supplier')->nullable();
            $table->string('supplier_invoice')->nullable();
            $table->foreignId('production_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total', 16, 2)->default(0);
            $table->decimal('paid_amount', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('purchased_at');
        });

        Schema::create('material_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            // Satuan beli: pcs/kg/lembar, atau jumlah batang untuk pipa.
            $table->decimal('qty', 14, 3);
            $table->decimal('bar_length_cm', 10, 2)->nullable();
            $table->decimal('unit_price', 16, 2);
            $table->decimal('subtotal', 16, 2);
            $table->timestamps();
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 16, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_number')->unique();
            $table->date('produced_at');
            $table->foreignId('formula_id')->constrained()->restrictOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_plan_line_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('qty', 12, 3);
            $table->string('worker')->nullable();
            // draft | posted
            $table->string('status', 10)->default('draft');
            $table->decimal('material_cost', 16, 2)->default(0);
            $table->decimal('waste_cost', 16, 2)->default(0);
            $table->decimal('other_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->decimal('hpp_per_unit', 16, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'produced_at']);
        });

        Schema::create('production_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('part_name');
            $table->string('component_name');
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_cuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pipe_piece_id')->constrained()->restrictOnDelete();
            $table->string('label');
            $table->decimal('length_cm', 10, 2);
            $table->decimal('kerf_cm', 10, 2)->default(0);
            // Keadaan potongan sebelum dipotong, untuk membatalkan produksi.
            $table->foreignId('before_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('before_length_cm', 10, 2);
            $table->string('before_status', 10);
            $table->decimal('cost', 16, 2)->default(0);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();
        });

        Schema::create('material_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique();
            $table->date('adjusted_at');
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('pipe_piece_id')->nullable()->constrained()->nullOnDelete();
            // in | out
            $table->string('direction', 3);
            $table->decimal('qty', 14, 3);
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('unit_cost', 16, 4)->default(0);
            // stok_awal | koreksi | rusak
            $table->string('reason', 20);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Pembangunan ulang tidak dibalik: pulihkan dari cadangan database.
    }
};
