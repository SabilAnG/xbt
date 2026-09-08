<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Models\Warehouse;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        $guard = TableActions::notInUse(['racks' => 'rak']);

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Gudang')->searchable()->sortable()->weight('medium')
                    ->description(fn (Warehouse $r) => $r->description),

                TextColumn::make('code')->label('Kode')->badge()->color('gray')->searchable(),

                TextColumn::make('racks_count')->counts('racks')->label('Rak')->alignCenter()->badge(),

                TextColumn::make('nilai')
                    ->label('Nilai Bahan')
                    ->alignRight()
                    ->money('IDR')
                    ->getStateUsing(fn (Warehouse $r) => $r->stockValue())
                    ->description('stok x harga beli'),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada gudang')
            ->emptyStateDescription('Buat gudang dulu, lalu tambahkan rak di dalamnya.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
