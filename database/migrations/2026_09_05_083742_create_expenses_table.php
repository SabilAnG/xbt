<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengeluaran di luar pembelian barang — jasa las, listrik, gaji, dst.
     * Jenisnya diturunkan dari kategori (expense_categories.expense_type_id).
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->date('spent_at');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 16, 2)->default(0);
            $table->string('description')->nullable();
            $table->string('paid_to')->nullable();

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
