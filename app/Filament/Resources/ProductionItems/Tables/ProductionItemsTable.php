<?php

namespace App\Filament\Resources\ProductionItems\Tables;

use App\Models\ProductionItem;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),

                TextColumn::make('name')->label('Barang')->searchable()->sortable()->wrap()
                    ->description(fn (ProductionItem $r) => trim(implode(' · ', array_filter([
                        $r->category?->name,
                        $r->material ? $r->displayMaterial() : null,
                        $r->displayDimensions() !== '—' ? $r->displayDimensions() : null,
                    ])))),

                TextColumn::make('role')
                    ->label('Peran')->badge()
                    ->formatStateUsing(fn (ProductionItem $r) => $r->displayRole())
                    ->color(fn (string $state) => match ($state) {
                        'utama' => 'primary',
                        'aksesoris' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Didapat dari')->badge()
                    ->formatStateUsing(fn (ProductionItem $r) => $r->displaySource())
                    ->color(fn (string $state) => match ($state) {
                        'produksi' => 'warning',
                        'beli_produksi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('konversi')
                    ->label('Konversi')->toggleable()
                    ->getStateUsing(fn (ProductionItem $r) => $r->conversionLabel()),

                TextColumn::make('cost_price')
                    ->label('Harga Beli')->money('IDR')->alignRight()->sortable()
                    ->description(fn (ProductionItem $r) => $r->displayBasePrice()),

                TextColumn::make('stock')
                    ->label('Stok')->alignRight()->sortable()
                    ->getStateUsing(fn (ProductionItem $r) => $r->displayStock())
                    ->color(fn (ProductionItem $r) => (float) $r->stock <= (float) $r->min_stock ? 'danger' : null)
                    ->description(fn (ProductionItem $r) => (float) $r->min_stock > 0
                        ? 'min '.$r->formatBase((float) $r->min_stock)
                        : null),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('production_item_category_id')->label('Jenis')
                    ->relationship('category', 'name')->searchable()->preload(),

                SelectFilter::make('role')->label('Peran')->options(ProductionItem::ROLES),

                SelectFilter::make('source')->label('Didapat dari')->options(ProductionItem::SOURCES),

                SelectFilter::make('shape')->label('Bentuk')->options(ProductionItem::SHAPES),

                SelectFilter::make('material')->label('Bahan')->options(ProductionItem::MATERIALS),

                Filter::make('menipis')
                    ->label('Stok menipis / habis')
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<=', 'min_stock')),
            ])
            ->headerActions([
                TableExport::action('barang-produksi', [
                    'SKU' => fn (ProductionItem $r) => $r->sku,
                    'Nama' => fn (ProductionItem $r) => $r->name,
                    'Jenis' => fn (ProductionItem $r) => $r->category?->name,
                    'Didapat dari' => fn (ProductionItem $r) => $r->displaySource(),
                    'Peran' => fn (ProductionItem $r) => $r->displayRole(),
                    'Bentuk' => fn (ProductionItem $r) => $r->displayShape(),
                    'Bahan' => fn (ProductionItem $r) => $r->displayMaterial(),
                    'Ukuran' => fn (ProductionItem $r) => $r->displayDimensions(),
                    'Satuan Beli' => fn (ProductionItem $r) => $r->unit,
                    'Harga Beli' => fn (ProductionItem $r) => $r->cost_price,
                    'Harga per Satuan Pakai' => fn (ProductionItem $r) => $r->basePrice(),
                    'Stok' => fn (ProductionItem $r) => $r->stock,
                    'Sisa Terkecil Terpakai' => fn (ProductionItem $r) => $r->min_reusable,
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(self::penjagaHapus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(self::penjagaHapus())]),
            ])
            ->emptyStateHeading('Belum ada barang produksi')
            ->emptyStateDescription('Mulai dari yang paling sering dipakai: pipa, plat, lalu aksesoris.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    /**
     * Barang yang stoknya belum nol tidak boleh hilang begitu saja — nilainya
     * ikut lenyap tanpa jejak. Nolkan lewat stok opname dulu, supaya
     * penyusutannya tercatat sebagai koreksi, bukan sebagai data yang raib.
     */
    private static function penjagaHapus(): callable
    {
        return fn (ProductionItem $record) => abs((float) $record->stock) > 0.0001
            ? 'Stoknya masih '.$record->displayStock().'. Nolkan lewat stok opname dulu supaya nilainya tidak hilang tanpa catatan.'
            : null;
    }
}
