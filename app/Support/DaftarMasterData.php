<?php

namespace App\Support;

use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\PriceTier;
use App\Models\ProductionItem;
use App\Models\ProductionItemCategory;
use App\Models\ProductionService;
use App\Models\Wallet;
use App\Models\Warehouse;

/**
 * Daftar master data sederhana di panel baru.
 *
 * Delapan layar ini bentuknya sama: nama, slug, keterangan, aktif, urutan.
 * Menulis delapan controller dan enam belas view untuk itu berarti delapan
 * tempat yang harus diperbaiki tiap kali ada yang berubah — dan di panel lama
 * memang begitulah jadinya.
 *
 * Yang membedakan hanya ISIAN TAMBAHANNYA, jadi itu yang dideklarasikan di
 * sini, bukan disembunyikan di balik "generik". Layar yang punya kebutuhan
 * khusus berhak punya controllernya sendiri; yang tidak, cukup satu baris.
 */
class DaftarMasterData
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function semua(): array
    {
        return [
            'kategori-produk' => [
                'model' => ItemCategory::class,
                'judul' => 'Kategori Produk',
                'nama' => 'Nama Kategori',
                'petunjuk' => 'Full Set, Silincer, Leheran, Sparepart.',
            ],

            'produk' => [
                'model' => ItemType::class,
                'judul' => 'Produk',
                'nama' => 'Nama Produk',
            ],

            'brand-motor' => [
                'model' => MotorcycleBrand::class,
                'judul' => 'Brand Motor',
                'nama' => 'Nama Brand',
                'keterangan' => false,
            ],

            'type-motor' => [
                'model' => MotorcycleModel::class,
                'judul' => 'Type Motor',
                'nama' => 'Nama Type',
                'keterangan' => false,
                'tambahan' => [
                    'motorcycle_brand_id' => ['label' => 'Brand', 'jenis' => 'pilihan', 'sumber' => MotorcycleBrand::class, 'wajib' => true],
                    'year_from' => ['label' => 'Tahun mulai', 'jenis' => 'angka'],
                    'year_to' => ['label' => 'Tahun sampai', 'jenis' => 'angka'],
                ],
            ],

            'jenis-pengeluaran' => [
                'model' => ExpenseType::class,
                'judul' => 'Jenis Pengeluaran',
                'nama' => 'Nama Jenis',
            ],

            'kategori-pengeluaran' => [
                'model' => ExpenseCategory::class,
                'judul' => 'Kategori Pengeluaran',
                'nama' => 'Nama Kategori',
                'tambahan' => [
                    'expense_type_id' => ['label' => 'Jenis Pengeluaran', 'jenis' => 'pilihan', 'sumber' => ExpenseType::class, 'wajib' => true],
                ],
            ],

            'jenis-barang-produksi' => [
                'model' => ProductionItemCategory::class,
                'judul' => 'Jenis Barang Produksi',
                'nama' => 'Nama Jenis',
                'petunjuk' => 'Peran dan cara pengadaan di sini jadi bawaan untuk seluruh barang di bawahnya.',
                'tambahan' => [
                    'role' => ['label' => 'Perannya di produk', 'jenis' => 'pilihan', 'pilihan' => ProductionItem::ROLES, 'wajib' => true],
                    'source' => ['label' => 'Didapat dari', 'jenis' => 'pilihan', 'pilihan' => ProductionItem::SOURCES, 'wajib' => true],
                    'default_warehouse_id' => ['label' => 'Gudang bawaan', 'jenis' => 'pilihan', 'sumber' => Warehouse::class],
                ],
            ],

            'jasa-produksi' => [
                'model' => ProductionService::class,
                'judul' => 'Jasa Produksi',
                'nama' => 'Nama Jasa',
                'tambahan' => [
                    'unit' => ['label' => 'Satuan', 'jenis' => 'teks', 'petunjuk' => 'Contoh: pcs, jam, titik.'],
                    'rate' => ['label' => 'Tarif', 'jenis' => 'angka', 'awalan' => 'Rp'],
                ],
            ],
            'tingkatan-harga' => [
                'model' => PriceTier::class,
                'judul' => 'Tingkatan Harga',
                'nama' => 'Nama Tingkatan',
                'keterangan' => 'notes',
                'tambahan' => [
                    'margin_percent' => ['label' => 'Margin', 'jenis' => 'angka', 'akhiran' => '%'],
                    'fee_percent' => ['label' => 'Biaya', 'jenis' => 'angka', 'akhiran' => '%'],
                ],
            ],

            'gudang' => [
                'model' => Warehouse::class,
                'judul' => 'Gudang',
                'nama' => 'Nama Gudang',
                'slug' => false,
                'tambahan' => [
                    'code' => ['label' => 'Kode', 'jenis' => 'teks', 'wajib' => true, 'petunjuk' => 'Singkat dan tetap, mis. GBM.'],
                    'type' => ['label' => 'Jenis Gudang', 'jenis' => 'pilihan', 'pilihan' => Warehouse::TYPES, 'wajib' => true],
                ],
            ],

            'dompet' => [
                'model' => Wallet::class,
                'judul' => 'Dompet & Kas',
                'nama' => 'Nama Dompet',
                'slug' => false,
                'keterangan' => false,
                'tambahan' => [
                    'type' => ['label' => 'Jenis', 'jenis' => 'pilihan', 'pilihan' => Wallet::TYPES, 'wajib' => true],
                    'account_number' => ['label' => 'Nomor rekening', 'jenis' => 'teks'],
                    'holder_name' => ['label' => 'Atas nama', 'jenis' => 'teks'],
                    // Saldo berjalan TIDAK ada di sini: angkanya diturunkan dari
                    // mutasi kas, dan isian yang bisa diketik akan membuatnya
                    // berselisih dengan mutasinya sendiri.
                    'opening_balance' => ['label' => 'Saldo awal', 'jenis' => 'angka', 'awalan' => 'Rp'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function cari(string $jenis): ?array
    {
        $satu = self::semua()[$jenis] ?? null;

        return $satu === null ? null : $satu + [
            'slug' => true,
            // Nama KOLOM keterangannya, bukan sekadar ada atau tidak: sebagian
            // model memakai `description`, sebagian `notes`, sebagian tidak
            // punya sama sekali.
            'keterangan' => 'description',
            'urutan' => true,
            'petunjuk' => null,
            'tambahan' => [],
        ];
    }
}
