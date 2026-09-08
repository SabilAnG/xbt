<?php

namespace App\Filament\Resources\ExpenseTypes\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Table;

class ExpenseTypesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Jenis Pengeluaran',
            guardRelations: ['categories' => 'kategori'],
            emptyHeading: 'Belum ada jenis pengeluaran',
            emptyDescription: 'Contoh: Jasa, Barang, Operasional, Gaji.',
        );
    }
}
