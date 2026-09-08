<?php

namespace App\Filament\Resources\Vendors\Tables;

use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        $guard = TableActions::notInUse(['purchases' => 'nota pembelian bahan']);

        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Vendor')->searchable()->sortable()->weight('medium'),
                TextColumn::make('phone')->label('Telepon')->placeholder('—')->searchable(),
                TextColumn::make('address')->label('Alamat')->placeholder('—')->limit(40)->toggleable(),
                TextColumn::make('purchases_count')->counts('purchases')->label('Nota')->alignCenter()->badge(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada vendor')
            ->emptyStateDescription('Tambahkan supplier bahan baku di sini.')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }
}
