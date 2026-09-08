<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Barang di gudang.
     *
     * Terpisah dari tabel `products` (katalog website): satu barang boleh tidak
     * pernah tampil di website, dan sebaliknya. Kolom product_id menautkan
     * keduanya bila memang barang yang sama.
     *
     * `stock` adalah cache dari stock_movements, bukan angka yang diketik
     * manual — lihat App\Services\StockService.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('item_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('unit')->default('pcs');
            $table->decimal('cost_price', 16, 2)->default(0);
            $table->decimal('sell_price', 16, 2)->default(0);
            $table->decimal('stock', 16, 2)->default(0);
            $table->decimal('min_stock', 16, 2)->default(0);

            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['item_category_id', 'item_type_id']);
            $table->index('name');
        });

        // Satu knalpot bisa cocok untuk banyak type motor.
        Schema::create('item_motorcycle_model', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('motorcycle_model_id')->constrained()->cascadeOnDelete();
            $table->unique(['item_id', 'motorcycle_model_id'], 'item_model_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_motorcycle_model');
        Schema::dropIfExists('items');
    }
};
