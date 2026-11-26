<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seperti di EMS: Supplier jadi master, dan pembelian (pesanan) dipisah dari
 * penerimaan barang. Stok baru bertambah saat barang diterima, bisa bertahap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('purchased_at')->constrained()->nullOnDelete();
        });

        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->decimal('received_qty', 16, 3)->default(0)->after('qty');
            // Sisa pesanan yang tidak jadi datang (dibatalkan), tidak ditagih.
            $table->decimal('cancelled_qty', 16, 3)->default(0)->after('received_qty');
        });

        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('material_purchase_id')->constrained()->restrictOnDelete();
            $table->date('received_at');
            $table->string('received_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_purchase_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 16, 3);
            $table->timestamps();
        });

        // Nama pemasok yang sudah pernah diketik dijadikan master supplier.
        $names = DB::table('material_purchases')->whereNotNull('supplier')->distinct()->pluck('supplier')
            ->merge(DB::table('productions')->whereNotNull('vendor')->distinct()->pluck('vendor'))
            ->map(fn ($name) => trim((string) $name))->filter()->unique(fn ($name) => mb_strtolower($name));

        foreach ($names as $name) {
            $id = DB::table('suppliers')->insertGetId(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('material_purchases')->where('supplier', $name)->update(['supplier_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');

        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->dropColumn(['received_qty', 'cancelled_qty']);
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
