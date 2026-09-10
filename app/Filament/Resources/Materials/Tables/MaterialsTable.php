<?php

namespace App\Filament\Resources\Materials\Tables;

use App\Models\Material;
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

class MaterialsTable
{
    public static function configure(Table $table): Table
    {
        $guard = function (Material $m): ?string {
            if ($m->movements()->exists()) {
                return 'Bahan ini sudah punya kartu stok. Nonaktifkan saja agar riwayatnya utuh.';
            }
            if ($m->formulaLines()->exists()) {
                return 'Bahan ini dipakai di formula. Lepas dari formula dulu.';
            }

            return null;
        };

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),

                TextColumn::make('name')->label('Bahan')->searchable()->sortable()->wrap()
                    ->description(fn (Material $record) => $record->category?->name),

                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()->toggleable()
                    ->formatStateUsing(fn (Material $record) => $record->displayRole())
                    ->color(fn (string $state) => match ($state) {
                        'utama' => 'primary',
                        'aksesoris' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Didapat dari')
                    ->badge()->toggleable()
                    ->formatStateUsing(fn (Material $record) => $record->displaySource())
                    ->color(fn (string $state) => match ($state) {
                        'produksi' => 'warning',
                        'beli_produksi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('dimension_type')
                    ->label('Tipe')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'linear' => 'Linear',
                        'sheet' => 'Lembaran',
                        'weight' => 'Berat',
                        'volume' => 'Volume',
                        default => 'Satuan',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'linear' => 'info',
                        'sheet' => 'warning',
                        'weight' => 'success',
                        'volume' => 'primary',
                        default => 'gray',
                    })
                    ->toggleable(),

                // Inti tahap ini: satuan beli, dan berapa satuan pakai yang didapat.
                TextColumn::make('konversi')
                    ->label('Konversi')
                    ->getStateUsing(fn (Material $record) => $record->conversionLabel())
                    ->description(fn (Material $record) => 'beli per '.$record->unit),

                TextColumn::make('cost_price')
                    ->label('Harga Beli')
                    ->money('IDR')->alignRight()->sortable()
                    ->description(fn (Material $record) => 'per '.$record->unit),

                TextColumn::make('harga_pakai')
                    ->label('Harga Pakai')
                    ->alignRight()
                    ->weight('bold')
                    ->color('primary')
                    ->getStateUsing(fn (Material $record) => $record->displayBasePrice())
                    ->description('dipakai formula'),

                TextColumn::make('stock')
                    ->label('Stok')
                    ->alignRight()->sortable()->badge()
                    ->formatStateUsing(fn ($state, Material $record) => $record->formatBase((float) $state))
                    ->color(fn (Material $record) => (float) $record->stock <= 0
                        ? 'danger'
                        : ((float) $record->stock <= (float) $record->min_stock ? 'warning' : 'success')),

                TextColumn::make('nilai')
                    ->label('Nilai Stok')
                    ->alignRight()->money('IDR')
                    ->getStateUsing(fn (Material $record) => $record->stockValue()),

                TextColumn::make('lokasi')
                    ->label('Rak')
                    ->badge()
                    ->getStateUsing(fn (Material $record) => $record->stocks()->where('qty', '>', 0)
                        ->with('rack')->get()
                        ->map(fn ($s) => $s->rack?->code ?? '-')->take(3)->all())
                    ->placeholder('—')
                    ->toggleable(),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('material_category_id')->label('Kategori')
                    ->relationship('category', 'name')->searchable()->preload(),

                SelectFilter::make('dimension_type')->label('Tipe')
                    ->options(Material::DIMENSION_TYPES),

                SelectFilter::make('role')->label('Peran')
                    ->options(Material::ROLES),

                SelectFilter::make('source')->label('Didapat dari')
                    ->options(Material::SOURCES),

                Filter::make('menipis')
                    ->label('Stok menipis / habis')
                    ->query(fn (Builder $query) => $query->whereColumn('stock', '<=', 'min_stock')),
            ])
            ->headerActions([
                TableExport::action('stok-bahan', [
                    'SKU' => fn (Material $r) => $r->sku,
                    'Bahan' => fn (Material $r) => $r->name,
                    'Kategori' => fn (Material $r) => $r->category?->name,
                    'Tipe' => fn (Material $r) => $r->dimension_type,
                    'Satuan Beli' => fn (Material $r) => $r->unit,
                    'Isi per Satuan Beli' => fn (Material $r) => $r->baseQtyPerPurchaseUnit(),
                    'Satuan Pakai' => fn (Material $r) => $r->baseUnit(),
                    'Harga Beli' => fn (Material $r) => (float) $r->cost_price,
                    'Harga per Satuan Pakai' => fn (Material $r) => $r->basePrice(),
                    'Stok' => fn (Material $r) => (float) $r->stock,
                    'Stok Minimum' => fn (Material $r) => (float) $r->min_stock,
                    'Nilai Stok' => fn (Material $r) => $r->stockValue(),
                    'Rak' => fn (Material $r) => $r->stocks()->where('qty', '>', 0)
                        ->with('rack')->get()->map(fn ($s) => $s->rack?->code)->filter()->implode(', '),
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada bahan baku')
            ->emptyStateDescription('Daftarkan pipa, plat, core, dan hardware beserta dimensi satuan belinya.')
            ->emptyStateIcon('heroicon-o-circle-stack');
    }
}
