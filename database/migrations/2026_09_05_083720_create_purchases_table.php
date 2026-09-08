<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembelian (nota masuk).
     *
     * Selama status masih `draft` transaksi belum menyentuh stok maupun saldo
     * dompet. Stok dan kas baru bergerak ketika nota di-`post`.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('purchased_at');
            $table->string('supplier_name')->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount', 16, 2)->default(0);
            $table->decimal('shipping_cost', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
