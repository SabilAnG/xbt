<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Wallet;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan inventory di dashboard.
 *
 * Semua angka rupiah hanya menghitung nota berstatus `posted`, sama seperti
 * halaman Rekap — supaya dashboard dan laporan tidak pernah berbeda.
 */
class InventoryOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $bulanIni = [now()->startOfMonth(), now()->endOfMonth()];

        $nilaiStok = (float) Item::query()->sum(DB::raw('stock * cost_price'));
        $jumlahBarang = Item::where('is_active', true)->count();
        $menipis = Item::query()->whereColumn('stock', '<=', 'min_stock')->count();
        $habis = Item::query()->where('stock', '<=', 0)->count();

        $kas = (float) Wallet::where('is_active', true)->sum('current_balance');

        $penjualan = (float) Sale::posted()->whereBetween('sold_at', $bulanIni)->sum('total');
        $modal = (float) Sale::posted()->whereBetween('sold_at', $bulanIni)->sum('total_cost');
        $pembelian = (float) Purchase::posted()->whereBetween('purchased_at', $bulanIni)->sum('total');
        $pengeluaran = (float) Expense::posted()->whereBetween('spent_at', $bulanIni)->sum('amount');

        $labaKotor = $penjualan - $modal;
        $labaBersih = $labaKotor - $pengeluaran;

        // Sparkline penjualan 7 hari terakhir.
        $tren = collect(range(6, 0))->map(function (int $mundur) {
            $hari = now()->subDays($mundur)->toDateString();

            return (float) Sale::posted()->whereDate('sold_at', $hari)->sum('total');
        })->all();

        $rp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        return [
            Stat::make('Nilai Stok', $rp($nilaiStok))
                ->description($jumlahBarang.' barang aktif')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Saldo Kas', $rp($kas))
                ->description(Wallet::where('is_active', true)->count().' dompet aktif')
                ->descriptionIcon('heroicon-m-wallet')
                ->color($kas < 0 ? 'danger' : 'success'),

            Stat::make('Penjualan Bulan Ini', $rp($penjualan))
                ->description('Laba kotor '.$rp($labaKotor))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($tren)
                ->color('success'),

            Stat::make('Laba Bersih Bulan Ini', $rp($labaBersih))
                ->description('Setelah pengeluaran '.$rp($pengeluaran))
                ->descriptionIcon($labaBersih < 0 ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-banknotes')
                ->color($labaBersih < 0 ? 'danger' : 'success'),

            Stat::make('Pembelian Bulan Ini', $rp($pembelian))
                ->description('Barang masuk gudang')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info'),

            Stat::make('Stok Menipis', (string) $menipis)
                ->description($habis > 0 ? $habis.' di antaranya habis' : 'Tidak ada yang habis')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($menipis > 0 ? 'warning' : 'success'),

            Stat::make('Nota Draft', (string) (
                Purchase::where('status', 'draft')->count()
                + Sale::where('status', 'draft')->count()
                + Expense::where('status', 'draft')->count()
            ))
                ->description('Belum dibukukan')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),

            Stat::make('Barang Terjual Bulan Ini', (string) rtrim(rtrim(
                (string) DB::table('sale_items')
                    ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                    ->where('sales.status', 'posted')
                    ->whereBetween('sales.sold_at', $bulanIni)
                    ->sum('sale_items.qty'),
                '0'
            ), '.'))
                ->description('Total kuantitas keluar')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),
        ];
    }
}
