<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\Purchase;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap pembelian, penjualan dan pengeluaran untuk satu rentang tanggal.
 *
 * Hanya nota berstatus `posted` yang dihitung — draft sengaja diabaikan supaya
 * angka laporan sama dengan yang benar-benar menggerakkan stok dan kas.
 */
class LaporanRekap extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Rekap & Laporan';

    protected static ?string $title = 'Rekap & Laporan';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.laporan-rekap';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return 'Aset';
    }

    public function mount(): void
    {
        $this->form->fill([
            'dari' => now()->startOfMonth()->toDateString(),
            'sampai' => now()->endOfMonth()->toDateString(),
            'laporan' => 'semua',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal')->required()->live(),
                        DatePicker::make('sampai')->label('Sampai tanggal')->required()->live(),
                        Select::make('laporan')
                            ->label('Tampilkan')
                            ->options([
                                'semua' => 'Semua',
                                'pembelian' => 'Pembelian',
                                'penjualan' => 'Penjualan',
                                'pengeluaran' => 'Pengeluaran',
                                'bahan' => 'Pembelian Bahan',
                                'produksi' => 'Produksi',
                            ])
                            ->default('semua')
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Cetak laporan')
                ->icon('heroicon-o-printer')
                ->color('gray')
                // Cetak lewat browser; stylesheet cetak menyembunyikan navigasi.
                ->extraAttributes(['onclick' => 'window.print()']),

            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action('exportCsv'),
        ];
    }

    // ------------------------------------------------------------------ data

    private function range(): array
    {
        $dari = $this->data['dari'] ?? now()->startOfMonth()->toDateString();
        $sampai = $this->data['sampai'] ?? now()->endOfMonth()->toDateString();

        return [Carbon::parse($dari)->startOfDay(), Carbon::parse($sampai)->endOfDay()];
    }

    public function getPurchasesProperty()
    {
        [$dari, $sampai] = $this->range();

        return Purchase::posted()
            ->with('wallet')
            ->whereBetween('purchased_at', [$dari, $sampai])
            ->orderBy('purchased_at')
            ->get();
    }

    public function getSalesProperty()
    {
        [$dari, $sampai] = $this->range();

        return Sale::posted()
            ->with('wallet')
            ->whereBetween('sold_at', [$dari, $sampai])
            ->orderBy('sold_at')
            ->get();
    }

    public function getMaterialPurchasesProperty()
    {
        [$dari, $sampai] = $this->range();

        return MaterialPurchase::posted()
            ->with(['vendor', 'wallet'])
            ->whereBetween('purchased_at', [$dari, $sampai])
            ->orderBy('purchased_at')
            ->get();
    }

    public function getProductionsProperty()
    {
        [$dari, $sampai] = $this->range();

        return Production::posted()
            ->with('formula')
            ->whereBetween('produced_at', [$dari, $sampai])
            ->orderBy('produced_at')
            ->get();
    }

    public function getExpensesProperty()
    {
        [$dari, $sampai] = $this->range();

        return Expense::posted()
            ->with(['category.type', 'wallet'])
            ->whereBetween('spent_at', [$dari, $sampai])
            ->orderBy('spent_at')
            ->get();
    }

    /**
     * @return array<string, float>
     */
    public function getSummaryProperty(): array
    {
        $pembelian = (float) $this->purchases->sum('total');
        $penjualan = (float) $this->sales->sum('total');
        $modal = (float) $this->sales->sum('total_cost');
        $pengeluaran = (float) $this->expenses->sum('amount');
        $labaKotor = $penjualan - $modal;

        // Angka produksi ditampilkan terpisah dan TIDAK ikut mengurangi laba.
        // Belanja bahan sudah menjadi nilai stok, dan biaya produksi sudah
        // masuk ke modal barang saat terjual — menjumlahkannya lagi di sini
        // akan menghitung pengeluaran yang sama dua kali.
        $belanjaBahan = (float) $this->materialPurchases->sum('total');
        $biayaProduksi = (float) $this->productions->sum('total_cost');
        $unitProduksi = (float) $this->productions->sum('output_qty');

        return [
            'pembelian' => $pembelian,
            'penjualan' => $penjualan,
            'modal' => $modal,
            'laba_kotor' => $labaKotor,
            'pengeluaran' => $pengeluaran,
            'laba_bersih' => $labaKotor - $pengeluaran,
            'belanja_bahan' => $belanjaBahan,
            'biaya_produksi' => $biayaProduksi,
            'unit_produksi' => $unitProduksi,
            'hpp_rata' => $unitProduksi > 0 ? $biayaProduksi / $unitProduksi : 0.0,
        ];
    }

    // ------------------------------------------------------- rekap per grup

    /**
     * Penjualan dikelompokkan per kategori atau jenis barang.
     * Hanya baris dari nota yang sudah dibukukan yang ikut dihitung.
     *
     * @return Collection<int, object>
     */
    private function salesGroupedBy(string $joinTable, string $foreignKey, string $label)
    {
        [$dari, $sampai] = $this->range();

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('items', 'items.id', '=', 'sale_items.item_id')
            ->leftJoin($joinTable, "{$joinTable}.id", '=', "items.{$foreignKey}")
            ->where('sales.status', 'posted')
            ->whereBetween('sales.sold_at', [$dari, $sampai])
            ->groupBy("{$joinTable}.id", "{$joinTable}.name")
            ->orderByDesc('omzet')
            ->get([
                DB::raw("COALESCE({$joinTable}.name, 'Tanpa {$label}') as nama"),
                DB::raw('SUM(sale_items.qty) as qty'),
                DB::raw('SUM(sale_items.subtotal) as omzet'),
                DB::raw('SUM(sale_items.qty * sale_items.unit_cost) as modal'),
            ]);
    }

    public function getSalesByCategoryProperty()
    {
        return $this->salesGroupedBy('item_categories', 'item_category_id', 'Kategori');
    }

    public function getSalesByTypeProperty()
    {
        return $this->salesGroupedBy('item_types', 'item_type_id', 'Jenis');
    }

    /** Barang terlaris pada periode ini. */
    public function getTopItemsProperty()
    {
        [$dari, $sampai] = $this->range();

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('items', 'items.id', '=', 'sale_items.item_id')
            ->where('sales.status', 'posted')
            ->whereBetween('sales.sold_at', [$dari, $sampai])
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('omzet')
            ->limit(10)
            ->get([
                'items.name as nama',
                DB::raw('SUM(sale_items.qty) as qty'),
                DB::raw('SUM(sale_items.subtotal) as omzet'),
            ]);
    }

    /** Pengeluaran dikelompokkan per jenis (Jasa, Barang, Operasional, ...). */
    public function getExpensesByTypeProperty()
    {
        [$dari, $sampai] = $this->range();

        return DB::table('expenses')
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('expense_types', 'expense_types.id', '=', 'expense_categories.expense_type_id')
            ->where('expenses.status', 'posted')
            ->whereBetween('expenses.spent_at', [$dari, $sampai])
            ->groupBy('expense_types.id', 'expense_types.name')
            ->orderByDesc('jumlah')
            ->get([
                DB::raw("COALESCE(expense_types.name, 'Tanpa jenis') as nama"),
                DB::raw('COUNT(*) as banyak'),
                DB::raw('SUM(expenses.amount) as jumlah'),
            ]);
    }

    /** Arus kas harian untuk grafik batang. */
    public function getDailyFlowProperty()
    {
        [$dari, $sampai] = $this->range();

        $masuk = $this->sales->groupBy(fn ($s) => $s->sold_at->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $keluarBeli = $this->purchases->groupBy(fn ($p) => $p->purchased_at->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum('total'));

        $keluarBiaya = $this->expenses->groupBy(fn ($e) => $e->spent_at->format('Y-m-d'))
            ->map(fn ($g) => (float) $g->sum('amount'));

        $days = collect($masuk->keys())
            ->merge($keluarBeli->keys())
            ->merge($keluarBiaya->keys())
            ->unique()->sort()->values();

        return $days->map(fn ($d) => (object) [
            'tanggal' => $d,
            'masuk' => $masuk[$d] ?? 0.0,
            'keluar' => ($keluarBeli[$d] ?? 0.0) + ($keluarBiaya[$d] ?? 0.0),
        ]);
    }

    public function shows(string $key): bool
    {
        $pick = $this->data['laporan'] ?? 'semua';

        return $pick === 'semua' || $pick === $key;
    }

    // ---------------------------------------------------------------- export

    public function exportCsv(): StreamedResponse
    {
        [$dari, $sampai] = $this->range();
        $filename = sprintf('rekap-%s-sd-%s.csv', $dari->format('Ymd'), $sampai->format('Ymd'));

        $purchases = $this->purchases;
        $sales = $this->sales;
        $expenses = $this->expenses;
        $materialPurchases = $this->materialPurchases;
        $productions = $this->productions;
        $summary = $this->summary;
        $shows = fn (string $k) => $this->shows($k);

        return response()->streamDownload(function () use ($purchases, $sales, $expenses, $materialPurchases, $productions, $summary, $shows) {
            $out = fopen('php://output', 'wb');
            // BOM supaya Excel membaca UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            if ($shows('pembelian')) {
                fputcsv($out, ['PEMBELIAN']);
                fputcsv($out, ['Tanggal', 'No. Nota', 'Supplier', 'Dompet', 'Total']);
                foreach ($purchases as $p) {
                    fputcsv($out, [
                        $p->purchased_at?->format('Y-m-d'),
                        $p->invoice_number,
                        $p->supplier_name,
                        $p->wallet?->name,
                        (float) $p->total,
                    ]);
                }
                fputcsv($out, ['', '', '', 'Subtotal', $summary['pembelian']]);
                fputcsv($out, []);
            }

            if ($shows('penjualan')) {
                fputcsv($out, ['PENJUALAN']);
                fputcsv($out, ['Tanggal', 'No. Nota', 'Pembeli', 'Dompet', 'Total', 'Modal', 'Laba']);
                foreach ($sales as $s) {
                    fputcsv($out, [
                        $s->sold_at?->format('Y-m-d'),
                        $s->invoice_number,
                        $s->customer_name,
                        $s->wallet?->name,
                        (float) $s->total,
                        (float) $s->total_cost,
                        $s->grossProfit(),
                    ]);
                }
                fputcsv($out, ['', '', '', 'Subtotal', $summary['penjualan'], $summary['modal'], $summary['laba_kotor']]);
                fputcsv($out, []);
            }

            if ($shows('pengeluaran')) {
                fputcsv($out, ['PENGELUARAN']);
                fputcsv($out, ['Tanggal', 'No. Bukti', 'Kategori', 'Jenis', 'Kepada', 'Dompet', 'Jumlah']);
                foreach ($expenses as $e) {
                    fputcsv($out, [
                        $e->spent_at?->format('Y-m-d'),
                        $e->reference_number,
                        $e->category?->name,
                        $e->category?->type?->name,
                        $e->paid_to,
                        $e->wallet?->name,
                        (float) $e->amount,
                    ]);
                }
                fputcsv($out, ['', '', '', '', '', 'Subtotal', $summary['pengeluaran']]);
                fputcsv($out, []);
            }

            if ($shows('bahan')) {
                fputcsv($out, ['PEMBELIAN BAHAN BAKU']);
                fputcsv($out, ['Tanggal', 'No. Nota', 'Vendor', 'Dompet', 'Total']);
                foreach ($materialPurchases as $b) {
                    fputcsv($out, [
                        $b->purchased_at?->format('Y-m-d'),
                        $b->invoice_number,
                        $b->vendor?->name,
                        $b->wallet?->name,
                        (float) $b->total,
                    ]);
                }
                fputcsv($out, ['', '', '', 'Subtotal', $summary['belanja_bahan']]);
                fputcsv($out, []);
            }

            if ($shows('produksi')) {
                fputcsv($out, ['PRODUKSI']);
                fputcsv($out, ['Tanggal', 'No. Produksi', 'Formula', 'Unit', 'Biaya Bahan',
                    'Tenaga Kerja', 'Menit Kerja', 'Biaya Mesin', 'Overhead', 'Total', 'HPP/unit']);
                foreach ($productions as $p) {
                    fputcsv($out, [
                        $p->produced_at?->format('Y-m-d'),
                        $p->production_number,
                        $p->formula?->name,
                        (float) $p->output_qty,
                        (float) $p->material_cost,
                        (float) $p->service_cost,
                        (float) $p->total_minutes,
                        (float) $p->machine_cost,
                        (float) $p->overhead_cost,
                        (float) $p->total_cost,
                        (float) $p->hpp_per_unit,
                    ]);
                }
                fputcsv($out, ['', '', 'Subtotal', $summary['unit_produksi'], '', '', '', '', '',
                    $summary['biaya_produksi'], $summary['hpp_rata']]);
                fputcsv($out, []);
            }

            fputcsv($out, ['RINGKASAN']);
            fputcsv($out, ['Total pembelian', $summary['pembelian']]);
            fputcsv($out, ['Total penjualan', $summary['penjualan']]);
            fputcsv($out, ['Modal terjual', $summary['modal']]);
            fputcsv($out, ['Laba kotor', $summary['laba_kotor']]);
            fputcsv($out, ['Total pengeluaran', $summary['pengeluaran']]);
            fputcsv($out, ['Laba bersih', $summary['laba_bersih']]);
            fputcsv($out, []);
            fputcsv($out, ['PRODUKSI (di luar hitungan laba)']);
            fputcsv($out, ['Belanja bahan baku', $summary['belanja_bahan']]);
            fputcsv($out, ['Biaya produksi', $summary['biaya_produksi']]);
            fputcsv($out, ['Unit diproduksi', $summary['unit_produksi']]);
            fputcsv($out, ['HPP rata-rata', $summary['hpp_rata']]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
