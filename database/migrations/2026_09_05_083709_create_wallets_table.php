<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dompet — kas laci, rekening bank, e-wallet.
     *
     * current_balance adalah cache dari wallet_transactions, bukan sumber
     * kebenaran; nilainya dihitung ulang setiap ada mutasi.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('cash'); // cash | bank | ewallet
            $table->string('account_number')->nullable();
            $table->string('holder_name')->nullable();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
