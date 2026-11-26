<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang Aksesoris, formula untuk barang setengah jadi, dan produksi yang
 * dikerjakan vendor (kirim bahan → terima hasil + tagihan jasa).
 */
return new class extends Migration
{
    public function up(): void
    {
        $warehouses = [
            ['bahan_baku', 1],
            ['setengah_jadi', 2],
            ['aksesoris', 3, 'Gudang Aksesoris', 'GD-AKS', 'Baut, stiker/emblem, tameng, karet, packing, dus.'],
            ['sisa_produksi', 4],
            ['di_vendor', 5, 'Gudang Di Vendor', 'GD-VND', 'Barang yang sedang dikerjakan vendor (bending, chrome, cat).'],
            ['finish_good', 6],
        ];

        foreach ($warehouses as $row) {
            [$type, $order] = $row;

            if (DB::table('warehouses')->where('type', $type)->exists()) {
                DB::table('warehouses')->where('type', $type)->update(['sort_order' => $order]);

                continue;
            }

            if (isset($row[2])) {
                DB::table('warehouses')->insert([
                    'name' => $row[2], 'code' => $row[3], 'type' => $type, 'description' => $row[4],
                    'is_active' => true, 'sort_order' => $order, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('formulas', function (Blueprint $table) {
            // knalpot → produk jadi (items); setengah_jadi → bahan di Gudang Setengah Jadi.
            $table->string('output_type', 20)->default('knalpot')->after('variant');
            $table->foreignId('output_material_id')->nullable()->after('item_id')->constrained('materials')->nullOnDelete();
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->unsignedBigInteger('item_id')->nullable()->change();
            // internal | vendor
            $table->string('mode', 10)->default('internal')->after('qty');
            $table->string('vendor')->nullable()->after('mode');
            $table->string('process')->nullable()->after('vendor');
            $table->decimal('service_cost_per_unit', 16, 2)->default(0)->after('process');
            $table->decimal('received_qty', 12, 3)->default(0)->after('service_cost_per_unit');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            // barang | jasa (tagihan jasa vendor dari penerimaan produksi)
            $table->string('kind', 10)->default('barang')->after('invoice_number');
        });

        Schema::create('production_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->date('received_at');
            $table->decimal('qty', 12, 3);
            $table->decimal('service_cost_per_unit', 16, 2)->default(0);
            $table->decimal('unit_cost', 16, 4)->default(0);
            $table->foreignId('material_purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_receipts');

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->dropColumn(['mode', 'vendor', 'process', 'service_cost_per_unit', 'received_qty']);
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('output_material_id');
            $table->dropColumn('output_type');
        });

        DB::table('warehouses')->whereIn('type', ['aksesoris', 'di_vendor'])->delete();
    }
};
