<?php

namespace App\Filament\Resources\Warehouses\Pages;

use App\Filament\Resources\ProductionItemOpnames\ProductionItemOpnameResource;
use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\ProductionItem;
use App\Models\ProductionItemStock;
use App\Models\Warehouse;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isi satu gudang.
 *
 * Dibuka dengan mengklik kartunya. Yang ditampilkan stok DI GUDANG INI, bukan
 * total barangnya — pertanyaan orang yang membuka halaman ini selalu "ada apa
 * di sini", bukan "berapa punya saya seluruhnya".
 */
class IsiGudang extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = WarehouseResource::class;

    protected string $view = 'filament.resources.warehouses.isi-gudang';

    public Warehouse $record;

    public function mount(Warehouse $record): void
    {
        $this->record = $record;
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $jumlah = $this->record->stocks()->where('qty', '!=', 0)->count();

        return $jumlah === 0
            ? 'Gudang ini masih kosong — isi lewat stok opname.'
            : $jumlah.' barang berstok · nilai isi Rp '.number_format($this->record->stockValue(), 0, ',', '.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ProductionItemStock::query()
                ->with(['item.category'])
                ->where('warehouse_id', $this->record->id))
            ->defaultSort('qty', 'desc')
            ->columns([
                TextColumn::make('item.name')
                    ->label('Barang')->searchable()->sortable()->wrap()
                    ->description(fn (ProductionItemStock $r) => trim(implode(' · ', array_filter([
                        $r->item?->sku,
                        $r->item?->category?->name,
                    ])))),

                TextColumn::make('item.role')
                    ->label('Peran')->badge()->toggleable()
                    ->formatStateUsing(fn (ProductionItemStock $r) => $r->item?->displayRole())
                    ->color(fn (?string $state) => match ($state) {
                        'utama' => 'primary',
                        'aksesoris_utama' => 'warning',
                        'aksesoris' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('qty')
                    ->label('Stok di gudang ini')->alignRight()->sortable()
                    ->getStateUsing(fn (ProductionItemStock $r) => $r->item?->formatBase((float) $r->qty) ?? $r->qty)
                    ->weight('medium'),

                TextColumn::make('nilai')
                    ->label('Nilai')->money('IDR')->alignRight()
                    ->getStateUsing(fn (ProductionItemStock $r) => (float) $r->qty * ($r->item?->basePrice() ?? 0)),

                TextColumn::make('total')
                    ->label('Total semua gudang')->alignRight()->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn (ProductionItemStock $r) => $r->item?->displayStock()),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(ProductionItem::ROLES)
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('item', fn (Builder $q) => $q->where('role', $data['value']))
                        : $query),
            ])
            // Yang diklik barisnya adalah stok di gudang ini, tapi yang ingin
            // diubah orang selalu barangnya — jadi modalnya membuka master
            // barang itu, bukan baris stoknya.
            ->recordActions([
                Action::make('ubahBarang')
                    ->label('Ubah barang')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->modalWidth(Width::FiveExtraLarge)
                    ->modalHeading(fn (?ProductionItemStock $record) => 'Ubah '.$record?->item?->name)
                    ->modalSubmitActionLabel('Simpan')
                    // Yang diisi form ini barangnya, bukan baris stoknya —
                    // tanpa ini `relationship()` di dalamnya dicari di
                    // ProductionItemStock dan tidak ketemu.
                    ->schema(fn (Schema $schema, ?ProductionItemStock $record) => ProductionItemForm::configure(
                        $record?->item
                            ? $schema->record($record->item)
                            : $schema->model(ProductionItem::class)
                    ))
                    ->fillForm(fn (?ProductionItemStock $record) => $record?->item?->attributesToArray() ?? [])
                    ->action(function (ProductionItemStock $record, array $data) {
                        $record->item?->update($data);

                        Notification::make()->success()->title('Barang disimpan')->send();
                    }),
            ])
            ->emptyStateHeading('Gudang ini masih kosong')
            ->emptyStateDescription('Stok masuk lewat stok opname — dan nanti lewat pembelian serta produksi.')
            ->emptyStateIcon('heroicon-o-archive-box');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('opname')
                ->label('Hitung Gudang Ini')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('primary')
                ->url(fn () => ProductionItemOpnameResource::getUrl('create')),

            Action::make('kembali')
                ->label('Semua Gudang')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => WarehouseResource::getUrl('index')),
        ];
    }
}
