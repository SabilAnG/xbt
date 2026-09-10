<?php

namespace App\Filament\Resources\ProductionItemCategories\Tables;

use App\Models\ProductionItemCategory;
use App\Support\MasterDataTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionItemCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Jenis Barang',
            guardRelations: ['items' => 'barang produksi'],
            extraColumns: [
                TextColumn::make('role')
                    ->label('Peran')->badge()
                    ->formatStateUsing(fn (ProductionItemCategory $r) => $r->displayRole())
                    ->color(fn (string $state) => match ($state) {
                        'utama' => 'primary',
                        'aksesoris_utama' => 'warning',
                        'aksesoris' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('source')
                    ->label('Didapat dari')->badge()
                    ->formatStateUsing(fn (ProductionItemCategory $r) => $r->displaySource())
                    ->color(fn (string $state) => match ($state) {
                        'produksi' => 'warning',
                        'beli_produksi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('items_count')
                    ->label('Barang')->counts('items')->alignCenter(),
            ],
            emptyHeading: 'Belum ada jenis barang',
            emptyDescription: 'Contoh: Pipa, Plat, Baut & Mur, Bahan Penolong.',
        );
    }
}
