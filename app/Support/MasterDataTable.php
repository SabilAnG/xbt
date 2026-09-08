<?php

namespace App\Support;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Bentuk tabel yang sama untuk semua master data, supaya perpindahan antar
 * menu terasa seragam. Kolom tambahan bisa disisipkan lewat $extraColumns.
 */
class MasterDataTable
{
    /**
     * @param  array<string, string>  $guardRelations  relasi => label, untuk menolak hapus yang masih dipakai
     * @param  array<int, mixed>  $extraColumns
     */
    public static function build(
        Table $table,
        string $nameLabel,
        array $guardRelations,
        array $extraColumns = [],
        bool $withDescription = true,
        bool $withSortOrder = true,
        string $emptyHeading = 'Belum ada data',
        ?string $emptyDescription = null,
    ): Table {
        $guard = TableActions::notInUse($guardRelations);

        $columns = [
            TextColumn::make('name')
                ->label($nameLabel)
                ->searchable()
                ->sortable()
                ->weight('medium'),
        ];

        $columns = array_merge($columns, $extraColumns);

        if ($withDescription) {
            $columns[] = TextColumn::make('description')
                ->label('Keterangan')
                ->limit(60)
                ->placeholder('—')
                ->toggleable();
        }

        // Berapa banyak transaksi/turunan yang menggantung pada baris ini.
        foreach ($guardRelations as $relation => $label) {
            $columns[] = TextColumn::make($relation.'_count')
                ->counts($relation)
                ->label(ucfirst($label))
                ->alignCenter()
                ->badge()
                ->color(fn ($state) => $state > 0 ? 'primary' : 'gray');
        }

        if ($withSortOrder) {
            $columns[] = TextColumn::make('sort_order')
                ->label('Urutan')
                ->alignCenter()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true);
        }

        $columns[] = IconColumn::make('is_active')->label('Aktif')->boolean()->sortable();

        return $table
            ->defaultSort($withSortOrder ? 'sort_order' : 'name')
            ->columns($columns)
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('Semua')
                    ->trueLabel('Aktif saja')
                    ->falseLabel('Nonaktif saja'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk($guard),
                ]),
            ])
            ->emptyStateHeading($emptyHeading)
            ->emptyStateDescription($emptyDescription)
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }
}
