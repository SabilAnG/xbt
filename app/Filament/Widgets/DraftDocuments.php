<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\Widget;

/**
 * Nota yang masih draft — belum menggerakkan stok maupun kas.
 *
 * Ketiganya model berbeda, jadi ditampilkan lewat view sendiri alih-alih
 * memaksakan union ke dalam satu tabel Eloquent.
 */
class DraftDocuments extends Widget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 1;

    protected string $view = 'filament.widgets.draft-documents';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGroups(): array
    {
        return [
            [
                'label' => 'Pembelian',
                'icon' => 'heroicon-m-arrow-down-tray',
                'color' => 'primary',
                'rows' => Purchase::where('status', 'draft')
                    ->latest('purchased_at')->limit(5)
                    ->get()
                    ->map(fn (Purchase $r) => [
                        'nomor' => $r->invoice_number,
                        'tanggal' => $r->purchased_at?->format('d M Y'),
                        'nilai' => (float) $r->total,
                        'url' => PurchaseResource::getUrl('edit', ['record' => $r]),
                    ])->all(),
                'total' => Purchase::where('status', 'draft')->count(),
            ],
            [
                'label' => 'Penjualan',
                'icon' => 'heroicon-m-arrow-up-tray',
                'color' => 'success',
                'rows' => Sale::where('status', 'draft')
                    ->latest('sold_at')->limit(5)
                    ->get()
                    ->map(fn (Sale $r) => [
                        'nomor' => $r->invoice_number,
                        'tanggal' => $r->sold_at?->format('d M Y'),
                        'nilai' => (float) $r->total,
                        'url' => SaleResource::getUrl('edit', ['record' => $r]),
                    ])->all(),
                'total' => Sale::where('status', 'draft')->count(),
            ],
            [
                'label' => 'Pengeluaran',
                'icon' => 'heroicon-m-banknotes',
                'color' => 'danger',
                'rows' => Expense::where('status', 'draft')
                    ->latest('spent_at')->limit(5)
                    ->get()
                    ->map(fn (Expense $r) => [
                        'nomor' => $r->reference_number,
                        'tanggal' => $r->spent_at?->format('d M Y'),
                        'nilai' => (float) $r->amount,
                        'url' => ExpenseResource::getUrl('edit', ['record' => $r]),
                    ])->all(),
                'total' => Expense::where('status', 'draft')->count(),
            ],
        ];
    }
}
