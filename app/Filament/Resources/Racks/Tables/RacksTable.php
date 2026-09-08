<?php

namespace App\Filament\Resources\Racks\Tables;

use App\Models\Rack;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RacksTable
{
    public static function configure(Table $table): Table
    {
        $guard = fn (Rack $r) => $r->stocks()->where('qty', '>', 0)->exists()
            ? 'Rak ini masih menyimpan bahan. Pindahkan isinya dulu.'
            : null;

        return $table
            ->defaultSort('code')
            ->defaultGroup('warehouse.name')
            ->columns([
                TextColumn::make('code')->label('Kode')->badge()->color('primary')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama / Posisi')->searchable()->wrap(),
                TextColumn::make('warehouse.name')->label('Gudang')->badge()->color('gray')->searchable()->toggleable(),

                TextColumn::make('isi')
                    ->label('Jenis bahan tersimpan')
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (Rack $r) => $r->stocks()->where('qty', '>', 0)->count())
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label('Gudang')
                    ->relationship('warehouse', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada rak')
            ->emptyStateDescription('Rak menentukan di mana bahan disimpan di dalam gudang.')
            ->emptyStateIcon('heroicon-o-table-cells');
    }
}
