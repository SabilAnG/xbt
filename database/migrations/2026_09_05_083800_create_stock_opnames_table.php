<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok opname — perhitungan fisik gudang.
     *
     * Saat di-`post`, setiap selisih antara stok sistem dan hitungan fisik
     * dibukukan sebagai satu baris stock_movements bertipe `opname`, sehingga
     * koreksi stok tetap punya jejak audit.
     */
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number')->unique();
            $table->date('opname_date');
            $table->string('counted_by')->nullable();

            $table->string('status')->default('draft'); // draft | posted
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'opname_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
