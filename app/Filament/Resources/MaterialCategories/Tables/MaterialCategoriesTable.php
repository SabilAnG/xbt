<?php

namespace App\Filament\Resources\MaterialCategories\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class MaterialCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Kategori Bahan',
            guardRelations: ['materials' => 'bahan'],
            emptyHeading: 'Belum ada kategori bahan',
            emptyDescription: 'Contoh: Pipa, Plat, Inlet, Baut.',
        );
    }
}
