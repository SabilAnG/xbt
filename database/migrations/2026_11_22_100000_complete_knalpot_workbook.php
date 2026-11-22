<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sisa sheet buku kerja knalpot: stok awal, "Dibayar dari", sheet Daftar
 * (pilihan dropdown, data host & tukang), Jam Live, Gaji Host, Absen Tukang,
 * Gaji Tukang, dan arsip Riwayat Pengadaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('opening_stock', 16, 2)->default(0)->after('sell_price_online');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('opening_stock', 16, 3)->default(0)->after('cost_price');
        });

        Schema::table('material_purchases', function (Blueprint $table) {
            $table->string('paid_from')->nullable()->after('recorded_in_expenses');
        });

        // Produk website yang diunggah dari stok knalpot. Katalog lama dari situs
        // sebelumnya berharga USD; produk dari stok berharga rupiah.
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('item_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('currency', 3)->default('USD')->after('price');
        });

        // Sheet Daftar: model motor, finishing, tipe, ukuran, merek, saluran jual.
        Schema::create('list_options', function (Blueprint $table) {
            $table->id();
            $table->string('list', 50);
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['list', 'value']);
        });

        Schema::create('hosts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('hourly_rate', 16, 2)->default(0);
            $table->string('account')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('host_id')->constrained()->restrictOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('manual_hours', 8, 2)->nullable();
            $table->string('store_account')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['host_id', 'date']);
        });

        Schema::create('host_payrolls', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('host_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('Belum'); // Belum | Sudah
            $table->date('paid_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('daily_wage', 16, 2)->default(0);
            $table->string('account')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attendance_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 3, 1); // 1 = masuk, 0,5 = setengah hari
            $table->timestamps();
            $table->unique(['attendance_day_id', 'worker_id']);
        });

        Schema::create('worker_payrolls', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->decimal('bonus', 16, 2)->default(0);
            $table->decimal('deduction', 16, 2)->default(0);
            $table->string('status')->default('Belum'); // Belum | Sudah
            $table->date('paid_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('number');
            $table->date('date');
            $table->string('period')->nullable();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->string('category')->nullable();
            $table->string('form')->nullable();
            $table->string('diameter')->nullable();
            $table->string('thickness')->nullable();
            $table->string('grade')->nullable();
            $table->decimal('qty', 16, 3)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_histories');
        Schema::dropIfExists('worker_payrolls');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_days');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('host_payrolls');
        Schema::dropIfExists('live_sessions');
        Schema::dropIfExists('hosts');
        Schema::dropIfExists('list_options');

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('item_id');
            $table->dropColumn('currency');
        });
        Schema::table('material_purchases', fn (Blueprint $table) => $table->dropColumn('paid_from'));
        Schema::table('materials', fn (Blueprint $table) => $table->dropColumn('opening_stock'));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('opening_stock'));
    }
};
