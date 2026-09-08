<?php

namespace App\Filament\Resources\ItemCategories\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class ItemCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Kategori Produk',
            guardRelations: ['items' => 'barang'],
            emptyHeading: 'Belum ada kategori barang',
            emptyDescription: 'Contoh: Full Set, Silincer, Leheran.',
        );
    }
}
