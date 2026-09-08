<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembelian bahan baku. Sama seperti nota lain di aplikasi ini: `draft`
     * belum menyentuh stok, stok baru bergerak saat nota di-`post`.
     *
     * Dompet dipakai bersama modul yang sudah ada agar kas tetap satu sumber.
     */
    public function up(): void
    {
        Schema::create('material_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->date('purchased_at');
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
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
        Schema::dropIfExists('material_purchases');
    }
};
