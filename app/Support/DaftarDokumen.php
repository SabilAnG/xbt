<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockOpname;

/**
 * Daftar nota operasional di panel baru.
 *
 * Empat dokumen ini bentuknya sama: punya nomor, tanggal, status draft atau
 * dibukukan, dan sepasang metode post/unpost di PostingService. Yang berbeda
 * hanya nama kolomnya dan isi barisnya — dan itu yang dideklarasikan di sini.
 *
 * Pembukuan TIDAK pernah ditulis ulang di panel; seluruhnya diserahkan ke
 * PostingService, satu-satunya tempat yang boleh menggerakkan stok dan kas.
 */
class DaftarDokumen
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function semua(): array
    {
        return [
            'pembelian' => [
                'model' => Purchase::class,
                'judul' => 'Pembelian',
                'petunjuk' => 'Nota beli barang jadi. Stok naik dan kas turun setelah dibukukan.',
                'nomor' => 'invoice_number',
                'tanggal' => 'purchased_at',
                'pihak' => ['kolom' => 'supplier_name', 'label' => 'Pemasok'],
                'post' => 'postPurchase',
                'unpost' => 'unpostPurchase',
                'baris' => ['qty' => 'qty', 'harga' => 'unit_cost', 'label_harga' => 'Harga Beli'],
            ],

            'penjualan' => [
                'model' => Sale::class,
                'judul' => 'Penjualan',
                'petunjuk' => 'Nota jual. Stok turun, kas naik, dan modalnya ikut dicatat.',
                'nomor' => 'invoice_number',
                'tanggal' => 'sold_at',
                'pihak' => ['kolom' => 'customer_name', 'label' => 'Pembeli'],
                'post' => 'postSale',
                'unpost' => 'unpostSale',
                'baris' => ['qty' => 'qty', 'harga' => 'unit_price', 'label_harga' => 'Harga Jual'],
            ],

            'pengeluaran' => [
                'model' => Expense::class,
                'judul' => 'Pengeluaran',
                'petunjuk' => 'Biaya di luar pembelian barang. Kas turun setelah dibukukan.',
                'nomor' => 'reference_number',
                'tanggal' => 'spent_at',
                'pihak' => ['kolom' => 'paid_to', 'label' => 'Dibayar ke'],
                'total' => 'amount',
                'post' => 'postExpense',
                'unpost' => 'unpostExpense',
                // Pengeluaran tidak punya baris barang — nilainya satu angka.
                'baris' => null,
            ],

            'opname-toko' => [
                'model' => StockOpname::class,
                'judul' => 'Stok Opname Toko',
                'petunjuk' => 'Menghitung fisik barang jadi, lalu mengoreksi selisihnya.',
                'nomor' => 'opname_number',
                'tanggal' => 'opname_date',
                'pihak' => ['kolom' => 'counted_by', 'label' => 'Dihitung oleh'],
                'total' => null,
                'post' => 'postOpname',

                // Tidak ada pembatalan untuk opname toko: PostingService
                // memang tidak menyediakannya. Menambahkan tombolnya di sini
                // berarti tombol yang pasti error saat ditekan.
                'unpost' => null,
                'baris' => ['selisih' => true],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function cari(string $jenis): ?array
    {
        $satu = self::semua()[$jenis] ?? null;

        return $satu === null ? null : $satu + [
            'total' => 'total',
            'petunjuk' => null,
            'baris' => null,
        ];
    }
}
