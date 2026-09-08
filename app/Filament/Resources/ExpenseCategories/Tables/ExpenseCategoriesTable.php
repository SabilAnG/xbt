<?php

namespace App\Filament\Resources\ExpenseCategories\Tables;

use App\Support\MasterDataTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return MasterDataTable::build(
            $table,
            nameLabel: 'Kategori Pengeluaran',
            guardRelations: ['expenses' => 'pengeluaran'],
            extraColumns: [
                TextColumn::make('type.name')
                    ->label('Jenis')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable(),
            ],
            withSortOrder: false,
            emptyHeading: 'Belum ada kategori pengeluaran',
            emptyDescription: 'Contoh: "Bayar Las" dengan jenis "Jasa".',
        )->groups(['type.name']);
    }
}
