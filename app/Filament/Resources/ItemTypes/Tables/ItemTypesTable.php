<?php

namespace App\Filament\Resources\ItemTypes\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class ItemTypesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Produk',
            guardRelations: ['items' => 'barang'],
            emptyHeading: 'Belum ada jenis barang',
            emptyDescription: 'Contoh: Knalpot Racing, Standar, Standar Racing.',
        );
    }
}
