<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian bahan produksi — pipa, plat, baut, pegas.
 *
 * Berbeda dari `purchases` yang membeli barang jual: yang ini menambah stok
 * Barang Produksi, dan karena stok bahan dihitung per gudang, notanya wajib
 * menyebut masuk ke gudang mana.
 *
 * Qty dan harga di barisnya memakai satuan BELI — batang, lembar, kg — persis
 * seperti yang tertulis di nota tokonya. Konversi ke satuan pakai dikerjakan
 * saat dibukukan, bukan diserahkan ke orang yang sedang menyalin nota.
 *
 * Stok dan kas baru bergerak saat status menjadi `posted`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('purchased_at');
            $table->string('supplier_name')->nullable();

            // Wajib: bahan yang dibeli harus mendarat di sebuah gudang.
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();

            // Opsional: nota bisa dicatat tanpa menyebut sumber dananya.
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount', 16, 2)->default(0);
            $table->decimal('shipping_cost', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);

            $table->string('status')->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'purchased_at']);
        });

        Schema::create('production_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_item_id')->constrained()->restrictOnDelete();

            // Dalam satuan beli: 7 batang, 2 lembar.
            $table->decimal('qty', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('subtotal', 16, 2)->default(0);

            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_purchase_items');
        Schema::dropIfExists('production_purchases');
    }
};
