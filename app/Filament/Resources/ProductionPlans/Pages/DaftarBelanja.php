<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\MaterialPurchases\MaterialPurchaseResource;
use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\Productions\ProductionResource;
use App\Models\PriceTier;
use App\Models\ProductionPlan;
use App\Models\Vendor;
use App\Services\ShoppingListService;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar belanja sebuah rencana produksi.
 *
 * Halaman baca saja: menampilkan kebutuhan gabungan, kekurangannya, dan berapa
 * satuan beli yang harus ditebus. Tombolnya membuat nota pembelian dan nota
 * produksi — keduanya DRAFT, jadi stok baru berubah setelah masing-masing
 * dibukukan.
 */
class DaftarBelanja extends Page
{
    protected static string $resource = ProductionPlanResource::class;

    protected string $view = 'filament.resources.production-plans.daftar-belanja';

    public ProductionPlan $record;

    public ?int $tierId = null;

    public function mount(ProductionPlan $record): void
    {
        $this->record = $record->load([
            'lines.formula.materials.material',
            'lines.formula.costs.component',
            'lines.formula.machines.machine',
            'materialPurchase',
        ]);

        $this->tierId = PriceTier::where('is_active', true)->orderByDesc('margin_percent')->value('id');
    }

    public function getTitle(): string
    {
        return 'Daftar Belanja '.$this->record->plan_number;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title
            ?: 'Periode '.$this->record->planned_for?->translatedFormat('F Y');
    }

    public function getTier(): ?PriceTier
    {
        return $this->tierId ? PriceTier::find($this->tierId) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatNota')
                ->label('Buat Nota Pembelian')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn () => ! $this->record->isShopped() && $this->record->shoppingList() !== [])
                ->schema([
                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->options(fn () => Vendor::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Boleh dikosongkan dan diisi nanti di notanya.'),
                ])
                ->modalHeading('Buat nota pembelian dari daftar ini?')
                ->modalDescription('Nota dibuat sebagai draft — harga dan jumlahnya masih bisa disesuaikan sebelum dibukukan.')
                ->action(function (array $data) {
                    try {
                        $nota = app(ShoppingListService::class)
                            ->createPurchase($this->record, $data['vendor_id'] ?? null);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()
                            ->title('Nota tidak dibuat')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    $this->record->refresh()->load('materialPurchase');

                    Notification::make()->success()
                        ->title('Nota '.$nota->invoice_number.' dibuat')
                        ->body('Periksa harga dan jumlahnya, lalu bukukan agar stok bertambah.')
                        ->send();

                    $this->redirect(MaterialPurchaseResource::getUrl('edit', ['record' => $nota]));
                }),

            Action::make('buatProduksi')
                ->label('Buat Nota Produksi')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->visible(fn () => ! $this->record->hasProductions() && $this->record->lines->isNotEmpty())
                ->requiresConfirmation()
                ->modalHeading('Buat nota produksi untuk seluruh rencana?')
                ->modalDescription('Satu nota draft per formula, isinya diambil dari resep. Stok bahan baru berkurang setelah masing-masing dibukukan.')
                ->action(function () {
                    try {
                        $notas = app(ShoppingListService::class)->createProductions($this->record);
                    } catch (RuntimeException $e) {
                        Notification::make()->danger()
                            ->title('Nota produksi tidak dibuat')->body($e->getMessage())->persistent()->send();

                        return;
                    }

                    $this->record->refresh();

                    Notification::make()->success()
                        ->title($notas->count().' nota produksi dibuat')
                        ->body('Periksa isinya, lalu bukukan satu per satu: '
                            .$notas->pluck('production_number')->implode(', '))
                        ->send();

                    $this->redirect(ProductionResource::getUrl('index'));
                }),

            Action::make('lihatNota')
                ->label('Lihat Nota Pembelian')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->visible(fn () => $this->record->isShopped())
                ->url(fn () => $this->record->materialPurchase
                    ? MaterialPurchaseResource::getUrl('edit', ['record' => $this->record->materialPurchase])
                    : null),

            Action::make('lihatProduksi')
                ->label('Lihat Nota Produksi')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->visible(fn () => $this->record->hasProductions())
                ->url(fn () => ProductionResource::getUrl('index')),

            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->unduh()),

            Action::make('kembali')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => ProductionPlanResource::getUrl('index')),
        ];
    }

    /** Berkas yang bisa dibawa ke toko bahan. */
    private function unduh(): StreamedResponse
    {
        $baris = $this->record->requirements();
        $nama = sprintf('belanja-%s.csv', $this->record->plan_number);

        return response()->streamDownload(function () use ($baris) {
            $out = fopen('php://output', 'wb');
            fwrite($out, TableExport::BOM);

            fputcsv($out, ['Bahan', 'SKU', 'Dibutuhkan', 'Stok', 'Kurang',
                'Beli', 'Satuan', 'Harga Satuan', 'Biaya', 'Sisa Setelah Beli']);

            foreach ($baris as $r) {
                fputcsv($out, [
                    $r['material']->name,
                    $r['material']->sku,
                    $r['butuh_label'],
                    $r['tersedia_label'],
                    $r['kurang_label'] ?? '',
                    $r['beli'] > 0 ? $r['beli'] : '',
                    $r['material']->unit,
                    $r['harga_satuan'],
                    $r['biaya'],
                    $r['material']->formatBase($r['sisa']),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, ['', '', '', '', '', '', '', 'Total belanja', $this->record->shoppingCost()]);

            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
