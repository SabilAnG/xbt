<?php

namespace App\Filament\Resources\MotorcycleBrands\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class MotorcycleBrandsTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Brand Motor',
            guardRelations: ['models' => 'type motor'],
            withDescription: false,
            emptyHeading: 'Belum ada brand motor',
            emptyDescription: 'Contoh: Honda, Yamaha, Harley-Davidson.',
        );
    }
}
