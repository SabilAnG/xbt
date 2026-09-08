<?php

namespace App\Filament\Widgets;

use App\Models\Formula;
use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Production;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan modul produksi di dashboard.
 *
 * Terpisah dari InventoryOverview (barang jual) supaya angka gudang bahan tidak
 * tercampur dengan angka barang dagangan. Hanya nota `posted` yang dihitung,
 * sama seperti seluruh laporan lain.
 */
class ProductionOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Produksi';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $bulanIni = [now()->startOfMonth(), now()->endOfMonth()];

        // Tidak bisa dijumlah di SQL: stok dalam satuan dasar (mm, mm2, gram)
        // sedangkan cost_price per satuan beli (batang, lembar). Mengalikan
        // keduanya langsung membuat satu batang pipa bernilai ratusan juta.
        $nilaiBahan = Material::query()->get()->sum(fn (Material $m) => $m->stockValue());
        $bahanMenipis = Material::query()->whereColumn('stock', '<=', 'min_stock')->count();
        $bahanHabis = Material::query()->where('stock', '<=', 0)->count();

        $belanjaBahan = (float) MaterialPurchase::posted()
            ->whereBetween('purchased_at', $bulanIni)->sum('total');

        $produksi = Production::posted()->whereBetween('produced_at', $bulanIni);
        $unitDiproduksi = (float) (clone $produksi)->sum('output_qty');
        $biayaProduksi = (float) (clone $produksi)->sum('total_cost');
        $menitKerja = (float) (clone $produksi)->sum('total_minutes');
        $hppRata = $unitDiproduksi > 0 ? $biayaProduksi / $unitDiproduksi : 0;

        // Kapasitas menganggur: berapa unit lagi yang bisa dibuat dari stok
        // bahan yang ada, dijumlah dari semua formula aktif.
        $kapasitas = Formula::with(['materials.material'])
            ->where('is_active', true)
            ->get()
            ->sum(fn (Formula $f) => $f->capacity()['unit']);

        $rp = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
        $num = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');

        return [
            Stat::make('Nilai Stok Bahan', $rp($nilaiBahan))
                ->description(Material::where('is_active', true)->count().' jenis bahan')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('primary'),

            Stat::make('Belanja Bahan Bulan Ini', $rp($belanjaBahan))
                ->description('Pipa, plat, dan komponen')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color('info'),

            Stat::make('Diproduksi Bulan Ini', $num($unitDiproduksi).' unit')
                ->description('Biaya '.$rp($biayaProduksi)
                    .($menitKerja > 0 ? ' · '.$num(round($menitKerja / 60, 1)).' jam kerja' : ''))
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('success'),

            Stat::make('HPP Rata-rata', $rp($hppRata))
                ->description($unitDiproduksi > 0 ? 'dari produksi bulan ini' : 'belum ada produksi')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($hppRata > 0 ? 'warning' : 'gray'),

            Stat::make('Bahan Menipis', (string) $bahanMenipis)
                ->description($bahanHabis > 0 ? $bahanHabis.' di antaranya habis' : 'Tidak ada yang habis')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($bahanMenipis > 0 ? 'danger' : 'success'),

            Stat::make('Masih Bisa Dibuat', $num((float) $kapasitas).' unit')
                ->description('Dari stok bahan sekarang, seluruh formula')
                ->descriptionIcon('heroicon-m-beaker')
                ->color($kapasitas > 0 ? 'success' : 'danger'),

            Stat::make('Formula Aktif', (string) Formula::where('is_active', true)->count())
                ->description('Resep siap dijalankan')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),

            Stat::make('Nota Produksi Draft', (string) (
                Production::where('status', 'draft')->count()
                + MaterialPurchase::where('status', 'draft')->count()
            ))
                ->description('Belum dibukukan')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray'),
        ];
    }
}
