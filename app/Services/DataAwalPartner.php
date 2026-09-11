<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\PriceTier;
use App\Models\ProductionItemCategory;
use App\Models\Wallet;
use App\Models\Warehouse;
use Illuminate\Support\Str;

/**
 * Mengisi master data secukupnya di toko partner yang baru dibuatkan.
 *
 * Migration sudah membawa gudang dan komponen knalpot. Sisanya kosong, dan toko
 * yang benar-benar kosong tidak bisa dipakai mencatat apa pun: tidak ada dompet
 * berarti tidak ada nota yang bisa dibukukan, tidak ada kategori berarti tidak
 * ada barang yang bisa didaftarkan.
 *
 * Yang diisi sengaja sedikit dan umum — yang berlaku di bengkel knalpot mana
 * pun. Partner menambah sendiri yang khas miliknya; yang penting hari pertama
 * tidak habis untuk mengisi daftar acuan sebelum sempat mencatat satu transaksi.
 */
class DataAwalPartner
{
    /** Dijalankan di dalam `$tenant->run()`. */
    public function isi(): void
    {
        $this->kategoriBarang();
        $this->jenisBarang();
        $this->jenisBarangProduksi();
        $this->tingkatHarga();
        $this->biaya();
        $this->dompet();
    }

    private function kategoriBarang(): void
    {
        $this->buat(ItemCategory::class, [
            'Full Set', 'Silincer', 'Leheran', 'Aksesoris',
        ]);
    }

    private function jenisBarang(): void
    {
        $this->buat(ItemType::class, ['Produk Jadi', 'Sparepart']);
    }

    private function jenisBarangProduksi(): void
    {
        $gudangMentah = Warehouse::where('type', 'bahan_mentah')->value('id');

        foreach ([
            ['Pipa', 'utama', 'beli'],
            ['Plat', 'utama', 'beli'],
            ['Baut & Mur', 'aksesoris_utama', 'beli'],
            ['Bahan Penolong', 'penolong', 'beli'],
        ] as [$nama, $peran, $sumber]) {
            ProductionItemCategory::firstOrCreate(
                ['slug' => Str::slug($nama)],
                [
                    'name' => $nama,
                    'role' => $peran,
                    'source' => $sumber,
                    'default_warehouse_id' => $gudangMentah,
                ],
            );
        }
    }

    /**
     * Margin dihitung dari harga jual, bukan dari modal — angka di bawah
     * mengikuti arti itu, dan partner tinggal menyesuaikan.
     */
    private function tingkatHarga(): void
    {
        foreach ([
            ['Umum', 35, 0],
            ['Reseller', 20, 0],
            ['Marketplace', 35, 10],
        ] as [$nama, $margin, $potongan]) {
            PriceTier::firstOrCreate(
                ['slug' => Str::slug($nama)],
                ['name' => $nama, 'margin_percent' => $margin, 'fee_percent' => $potongan],
            );
        }
    }

    private function biaya(): void
    {
        $jenis = [];

        foreach (['Operasional', 'Produksi'] as $nama) {
            $jenis[$nama] = ExpenseType::firstOrCreate(
                ['slug' => Str::slug($nama)],
                ['name' => $nama],
            )->getKey();
        }

        foreach ([
            ['Listrik', 'Operasional'],
            ['Sewa Tempat', 'Operasional'],
            ['Gaji', 'Operasional'],
            ['Alat & Perkakas', 'Produksi'],
            ['Ongkos Kirim', 'Operasional'],
        ] as [$nama, $indukNya]) {
            ExpenseCategory::firstOrCreate(
                ['slug' => Str::slug($nama)],
                ['name' => $nama, 'expense_type_id' => $jenis[$indukNya]],
            );
        }
    }

    /**
     * Satu dompet, saldo nol.
     *
     * Tanpa dompet tidak ada nota yang bisa dibukukan sama sekali. Saldonya
     * sengaja nol, bukan angka contoh — saldo palsu yang terlanjur dipercaya
     * lebih merepotkan daripada saldo kosong yang jelas perlu diisi.
     */
    private function dompet(): void
    {
        // Dompet dikenali dari namanya — tabelnya memang tidak berslug.
        Wallet::firstOrCreate(
            ['name' => 'Kas Toko'],
            ['type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0],
        );
    }

    /**
     * @param  class-string  $model
     * @param  array<int, string>  $nama
     */
    private function buat(string $model, array $nama): void
    {
        foreach ($nama as $i => $satu) {
            $model::firstOrCreate(
                ['slug' => Str::slug($satu)],
                ['name' => $satu, 'sort_order' => ($i + 1) * 10],
            );
        }
    }
}
