<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkronisasi lanjutan:
 * - cara bayar ongkos formula (termasuk gaji harian / borongan per produksi)
 *   dan upah borongan yang masuk slip gaji;
 * - jatuh tempo tagihan & termin bayar supplier;
 * - jejak aktivitas pengguna;
 * - harga katalog website mengikuti harga produk;
 * - retur pipa per batang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_options', function (Blueprint $table) {
            $table->string('payment_mode')->default('gaji')->after('amount');
        });

        Schema::create('worker_piece_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_payroll_id')->nullable()->constrained()->nullOnDelete();
            $table->date('earned_on');
            $table->string('name');
            $table->decimal('qty', 16, 2);
            $table->decimal('rate', 16, 2);
            $table->decimal('amount', 16, 2);
            $table->timestamps();
        });

        Schema::table('worker_payrolls', function (Blueprint $table) {
            $table->decimal('piece_total', 16, 2)->default(0)->after('overtime_total');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_term_days')->nullable()->after('address');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('purchased_at');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('price_follows_item')->default(false)->after('price');
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->foreignId('pipe_piece_id')->nullable()->after('warehouse_id')->constrained()->nullOnDelete();
        });

        // Produk web yang harganya sama dengan harga online produknya langsung ikut harga produk.
        foreach (DB::table('products')->whereNotNull('item_id')->get(['id', 'item_id', 'price']) as $product) {
            $online = DB::table('items')->where('id', $product->item_id)->value('sell_price_online');
            if ($online !== null && abs((float) $online - (float) $product->price) < 0.01) {
                DB::table('products')->where('id', $product->id)->update(['price_follows_item' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('pipe_piece_id'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('price_follows_item'));
        Schema::dropIfExists('activity_logs');
        Schema::table('material_purchases', fn (Blueprint $table) => $table->dropColumn('due_date'));
        Schema::table('suppliers', fn (Blueprint $table) => $table->dropColumn('payment_term_days'));
        Schema::table('worker_payrolls', fn (Blueprint $table) => $table->dropColumn('piece_total'));
        Schema::dropIfExists('worker_piece_earnings');
        Schema::table('cost_options', fn (Blueprint $table) => $table->dropColumn('payment_mode'));
    }
};
