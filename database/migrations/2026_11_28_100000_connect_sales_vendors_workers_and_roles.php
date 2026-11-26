<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyambungkan modul yang sebelumnya berdiri sendiri:
 * - penanggung jawab produksi → daftar Pekerja;
 * - pesanan/tracking website → nota penjualan;
 * - uang muka (DP) ke supplier/vendor dan pembayaran dari saldo DP;
 * - pelunasan piutang penjualan bertahap (riwayat pembayaran);
 * - penyesuaian/opname stok produk jadi;
 * - retur pembelian ke supplier;
 * - peran pengguna (hak akses menu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('worker_id')->nullable()->after('worker')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('sale_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::create('supplier_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 16, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreignId('supplier_deposit_id')->nullable()->after('wallet_id')->constrained()->nullOnDelete();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->date('paid_at');
            $table->decimal('amount', 16, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('item_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('adjustment_number')->unique();
            $table->date('adjusted_at');
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 16, 2);
            $table->decimal('actual_qty', 16, 2);
            $table->decimal('difference', 16, 2);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->string('reason');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('material_purchase_items', function (Blueprint $table) {
            $table->decimal('returned_qty', 16, 4)->default(0)->after('cancelled_qty');
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('material_purchase_id')->constrained()->cascadeOnDelete();
            $table->date('returned_at');
            $table->decimal('total', 16, 2)->default(0);
            $table->decimal('refund_amount', 16, 2)->default(0);
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_purchase_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->decimal('qty', 16, 4);
            $table->decimal('unit_price', 16, 2);
            $table->decimal('subtotal', 16, 2);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Penanggung jawab lama (teks) dicocokkan ke pekerja dengan nama yang sama.
        foreach (DB::table('workers')->get(['id', 'name']) as $worker) {
            DB::table('productions')->whereNull('worker_id')->whereRaw('LOWER(worker) = ?', [mb_strtolower($worker->name)])->update(['worker_id' => $worker->id]);
        }

        // Uang masuk penjualan lama menjadi pembayaran pertama; mutasi dompetnya ikut pindah.
        foreach (DB::table('sales')->where('paid_amount', '>', 0)->get() as $sale) {
            $paymentId = DB::table('sale_payments')->insertGetId([
                'sale_id' => $sale->id, 'wallet_id' => $sale->wallet_id, 'paid_at' => $sale->sold_at,
                'amount' => $sale->paid_amount, 'notes' => 'Dibayar saat transaksi', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('wallet_transactions')->where('source_type', 'App\\Models\\Sale')->where('source_id', $sale->id)
                ->update(['source_type' => 'App\\Models\\SalePayment', 'source_id' => $paymentId]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('sale_payments')->get() as $payment) {
            DB::table('wallet_transactions')->where('source_type', 'App\\Models\\SalePayment')->where('source_id', $payment->id)
                ->update(['source_type' => 'App\\Models\\Sale', 'source_id' => $payment->sale_id]);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'is_active']));
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::table('material_purchase_items', fn (Blueprint $table) => $table->dropColumn('returned_qty'));
        Schema::dropIfExists('item_adjustments');
        Schema::dropIfExists('sale_payments');
        Schema::table('purchase_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_deposit_id'));
        Schema::dropIfExists('supplier_deposits');
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
        Schema::table('productions', fn (Blueprint $table) => $table->dropConstrainedForeignId('worker_id'));
    }
};
