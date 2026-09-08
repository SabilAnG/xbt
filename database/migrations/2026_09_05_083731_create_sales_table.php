<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penjualan (nota keluar). Sama seperti pembelian: `draft` belum menyentuh
     * stok maupun dompet, `posted` baru membukukan keduanya.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('sold_at');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount', 16, 2)->default(0);
            $table->decimal('shipping_cost', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            // Modal barang saat terjual — disimpan agar laba tidak berubah
            // ketika harga beli barang diperbarui di kemudian hari.
            $table->decimal('total_cost', 16, 2)->default(0);

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
