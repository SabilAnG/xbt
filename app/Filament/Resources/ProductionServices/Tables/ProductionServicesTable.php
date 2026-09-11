<?php

namespace App\Filament\Resources\ProductionServices\Tables;

use App\Models\ProductionService;
use App\Support\MasterDataTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionServicesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Jasa',
            // Tarif yang sudah dipakai resep tidak boleh hilang diam-diam:
            // modal formula itu ikut turun tanpa jejak, dan turunnya baru
            // ketahuan saat harga jual sudah terlanjur ditetapkan.
            guardRelations: ['formulaServices' => 'formula'],
            extraColumns: [
                TextColumn::make('rate')
                    ->label('Tarif')->money('IDR')->alignRight()->sortable()
                    ->description(fn (ProductionService $r) => 'per '.$r->unit),
            ],
            emptyHeading: 'Belum ada jasa produksi',
            emptyDescription: 'Biaya membuat knalpot yang bukan bahan: chrome, poles, las argon, bending.',
        );
    }
}
