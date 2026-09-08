<?php

namespace App\Filament\Resources\MotorcycleModels\Tables;

use App\Models\MotorcycleModel;
use App\Support\MasterDataTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MotorcycleModelsTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Type Motor',
            guardRelations: ['items' => 'barang'],
            extraColumns: [
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tahun')
                    ->label('Tahun')
                    ->placeholder('—')
                    ->getStateUsing(function (MotorcycleModel $r) {
                        if (! $r->year_from && ! $r->year_to) {
                            return null;
                        }

                        return $r->year_from.' – '.($r->year_to ?: 'sekarang');
                    })
                    ->toggleable(),
            ],
            withDescription: false,
            withSortOrder: false,
            emptyHeading: 'Belum ada type motor',
            emptyDescription: 'Contoh: Vario 160 (Honda), Sportster (Harley-Davidson).',
        )->groups(['brand.name']);
    }
}
