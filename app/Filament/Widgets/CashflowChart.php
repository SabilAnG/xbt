<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;

/**
 * Uang masuk vs uang keluar per hari.
 */
class CashflowChart extends ChartWidget
{
    protected ?string $heading = 'Arus Kas';

    protected ?string $description = 'Uang masuk dari penjualan dibanding uang keluar untuk pembelian dan pengeluaran.';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 hari terakhir',
            '30' => '30 hari terakhir',
            '90' => '3 bulan terakhir',
        ];
    }

    protected function getData(): array
    {
        $hari = (int) ($this->filter ?? 30);
        $mulai = now()->subDays($hari - 1)->startOfDay();

        // Ambil sekali lalu kelompokkan di PHP — jumlah barisnya kecil dan
        // hasilnya sama untuk MySQL maupun SQLite.
        $masuk = Sale::posted()->where('sold_at', '>=', $mulai)
            ->get(['sold_at', 'total'])
            ->groupBy(fn ($r) => $r->sold_at->toDateString())
            ->map(fn ($g) => (float) $g->sum('total'));

        $beli = Purchase::posted()->where('purchased_at', '>=', $mulai)
            ->get(['purchased_at', 'total'])
            ->groupBy(fn ($r) => $r->purchased_at->toDateString())
            ->map(fn ($g) => (float) $g->sum('total'));

        $biaya = Expense::posted()->where('spent_at', '>=', $mulai)
            ->get(['spent_at', 'amount'])
            ->groupBy(fn ($r) => $r->spent_at->toDateString())
            ->map(fn ($g) => (float) $g->sum('amount'));

        $labels = [];
        $seriesMasuk = [];
        $seriesKeluar = [];

        for ($i = 0; $i < $hari; $i++) {
            $tanggal = $mulai->copy()->addDays($i);
            $key = $tanggal->toDateString();

            $labels[] = $tanggal->format($hari > 31 ? 'd/m' : 'd M');
            $seriesMasuk[] = $masuk[$key] ?? 0;
            $seriesKeluar[] = ($beli[$key] ?? 0) + ($biaya[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Uang masuk',
                    'data' => $seriesMasuk,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Uang keluar',
                    'data' => $seriesKeluar,
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
