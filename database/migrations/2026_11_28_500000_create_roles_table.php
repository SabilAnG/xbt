<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peran pengguna dengan daftar fitur (menu) yang boleh dibuka. Peran bawaan
 * dibuat dari aturan lama: Pemilik & Admin semua menu, Kepala Gudang dan
 * Kasir sesuai grup menunya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('all_access')->default(false);
            $table->boolean('is_system')->default(false);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        $groups = [
            'Pembelian' => ['purchases', 'receipts', 'suppliers'],
            'Penjualan' => ['finish-good', 'sales'],
            'Katalog' => ['items', 'units', 'brands', 'motor-options'],
            'Keuangan' => ['wallets', 'expenses', 'finance'],
            'Gudang' => ['warehouses', 'transfers', 'stock-opname', 'adjustments'],
            'Produksi' => ['formulas', 'component-options', 'vendor-services', 'cost-options', 'plans', 'productions', 'reports', 'vendor-production', 'offcut', 'production-settings'],
            'Pekerja' => ['workers', 'attendances', 'payrolls'],
            'Website' => ['site-settings', 'content-blocks', 'web-products', 'orders'],
        ];
        $pick = fn (array $names) => array_values(array_merge(...array_map(fn (string $group) => $groups[$group], $names)));

        DB::table('roles')->insert([
            ['key' => 'owner', 'name' => 'Pemilik', 'description' => 'Semua menu + kelola pengguna', 'all_access' => true, 'is_system' => true, 'permissions' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'admin', 'name' => 'Admin', 'description' => 'Semua menu kecuali kelola pengguna', 'all_access' => true, 'is_system' => false, 'permissions' => json_encode([]), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'gudang', 'name' => 'Kepala Gudang', 'description' => 'Pembelian, gudang, produksi, pekerja', 'all_access' => false, 'is_system' => false,
                'permissions' => json_encode($pick(['Pembelian', 'Katalog', 'Gudang', 'Produksi', 'Pekerja'])), 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'kasir', 'name' => 'Kasir / Penjualan', 'description' => 'Penjualan, keuangan, website', 'all_access' => false, 'is_system' => false,
                'permissions' => json_encode($pick(['Penjualan', 'Keuangan', 'Website'])), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
