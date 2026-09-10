<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable()->weight('medium'),

                TextColumn::make('name')->label('Gudang')->searchable()->sortable()->wrap()
                    ->description(fn (Warehouse $r) => $r->description),

                TextColumn::make('type')
                    ->label('Jenis')->badge()
                    ->formatStateUsing(fn (Warehouse $r) => $r->displayType())
                    ->color(fn (string $state) => match ($state) {
                        'bahan_mentah' => 'primary',
                        'setengah_jadi' => 'warning',
                        'finish_good' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('stocks_count')
                    ->label('Barang')->counts('stocks')->alignCenter(),

                TextColumn::make('nilai')
                    ->label('Nilai Isi')->money('IDR')->alignRight()
                    ->getStateUsing(fn (Warehouse $r) => $r->stockValue()),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(Warehouse::TYPES),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(self::penjagaHapus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(self::penjagaHapus())]),
            ])
            ->emptyStateHeading('Belum ada gudang')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }

    /**
     * Gudang yang masih berisi tidak boleh hilang: stoknya ikut lenyap tanpa
     * jejak. Pindahkan atau nolkan isinya lewat opname dulu.
     */
    private static function penjagaHapus(): callable
    {
        return function (Warehouse $record): ?string {
            $berisi = $record->stocks()->where('qty', '!=', 0)->count();

            return $berisi > 0
                ? "Masih ada {$berisi} barang berstok di gudang ini. Nolkan atau pindahkan lewat stok opname dulu."
                : null;
        };
    }
}
