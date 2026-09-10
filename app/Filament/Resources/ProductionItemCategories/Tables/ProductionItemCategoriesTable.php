<?php

namespace App\Filament\Resources\ProductionItemCategories\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class ProductionItemCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Jenis Barang Produksi',
            guardRelations: ['items' => 'barang produksi'],
            emptyHeading: 'Belum ada jenis barang produksi',
            emptyDescription: 'Contoh: Pipa, Plat, Hardware, Bahan Penolong.',
        );
    }
}
