<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ProductionItem;
use App\Models\ProductionPurchase;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Beranda panel.
 *
 * Angka rupiah HANYA menghitung nota berstatus `posted`, sama seperti halaman
 * Rekap — supaya dashboard dan laporan tidak pernah berbeda. Nota draft belum
 * menggerakkan apa pun, jadi memasukkannya di sini berarti menjanjikan uang
 * yang belum tentu ada.
 *
 * Berbeda dari dashboard panel lama, stok BAHAN ikut ditampilkan di sini —
 * di sana keempat widgetnya hanya membaca sisi toko, sehingga modul produksi
 * tidak pernah muncul di halaman depan sama sekali.
 */
class DasborController extends Controller
{
    public function __invoke(): View
    {
        $bulanIni = [now()->startOfMonth(), now()->endOfMonth()];

        $penjualan = (float) Sale::posted()->whereBetween('sold_at', $bulanIni)->sum('total');
        $modal = (float) Sale::posted()->whereBetween('sold_at', $bulanIni)->sum('total_cost');
        $pengeluaran = (float) Expense::posted()->whereBetween('spent_at', $bulanIni)->sum('amount');

        return view('panel.dasbor', [
            'nilaiStok' => (float) Item::query()->sum(DB::raw('stock * cost_price')),
            'jumlahBarang' => Item::where('is_active', true)->count(),
            'nilaiBahan' => (float) ProductionItem::query()->sum(DB::raw('stock * cost_price')),
            'jumlahBahan' => ProductionItem::where('is_active', true)->count(),
            'kas' => (float) Wallet::where('is_active', true)->sum('current_balance'),
            'jumlahDompet' => Wallet::where('is_active', true)->count(),
            'penjualan' => $penjualan,
            'labaKotor' => $penjualan - $modal,
            'labaBersih' => $penjualan - $modal - $pengeluaran,
            'pengeluaran' => $pengeluaran,

            // Tujuh hari terakhir untuk grafik batang sederhana.
            'tren' => collect(range(6, 0))->map(fn (int $mundur) => [
                'label' => now()->subDays($mundur)->translatedFormat('D'),
                'nilai' => (float) Sale::posted()->whereDate('sold_at', now()->subDays($mundur)->toDateString())->sum('total'),
            ])->all(),

            'menipis' => Item::query()->where('is_active', true)
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderBy('stock')->limit(8)->get(),

            'bahanMenipis' => ProductionItem::query()->where('is_active', true)
                ->whereColumn('stock', '<=', 'min_stock')
                ->orderBy('stock')->limit(8)->get(),

            'draf' => [
                'Pembelian' => Purchase::where('status', 'draft')->count(),
                'Penjualan' => Sale::where('status', 'draft')->count(),
                'Pengeluaran' => Expense::where('status', 'draft')->count(),
                'Pembelian Bahan' => ProductionPurchase::where('status', 'draft')->count(),
            ],
        ]);
    }
}
