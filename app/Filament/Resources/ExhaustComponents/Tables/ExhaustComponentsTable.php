<?php

namespace App\Filament\Resources\ExhaustComponents\Tables;

use App\Models\ExhaustComponent;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar komponen, dikelompokkan per bagian.
 *
 * Bengkel menyebutnya berkelompok — "yang di header", "yang di silincer" —
 * jadi daftarnya disusun begitu, bukan satu daftar panjang berabjad.
 */
class ExhaustComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->groups([
                Group::make('parent.name')
                    ->label('Bagian')
                    ->getTitleFromRecordUsing(fn (ExhaustComponent $r) => $r->parent?->name ?? 'Bagian Induk'),
            ])
            ->defaultGroup('parent.name')
            ->columns([
                TextColumn::make('name')
                    ->label('Komponen')->searchable()->sortable()->weight('medium')
                    ->description(fn (ExhaustComponent $r) => $r->code),

                TextColumn::make('bahan')
                    ->label('Bahan Baku')
                    ->getStateUsing(fn (ExhaustComponent $r) => $r->displayMaterial())
                    ->color(fn (ExhaustComponent $r) => match (true) {
                        $r->isBagian() => 'gray',
                        $r->item === null => 'danger',
                        default => null,
                    })
                    ->description(fn (ExhaustComponent $r) => $r->item?->conversionLabel()),

                TextColumn::make('children_count')
                    ->label('Isi')->counts('children')->alignCenter()
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state.' komponen' : '—')
                    ->toggleable(),

                TextColumn::make('sort_order')->label('Urutan')->alignCenter()->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Bagian')
                    ->options(fn () => ExhaustComponent::query()
                        ->bagian()->orderBy('sort_order')->pluck('name', 'id')),

                Filter::make('tanpa_bahan')
                    ->label('Bahan bakunya belum dipilih')
                    ->query(fn (Builder $query) => $query->tanpaBahan()),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(self::penjagaHapus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(self::penjagaHapus())]),
            ])
            ->emptyStateHeading('Belum ada komponen')
            ->emptyStateDescription('Mulai dari bagiannya — Header, Silincer — lalu isi komponennya.')
            ->emptyStateIcon('heroicon-o-puzzle-piece');
    }

    /**
     * Bagian yang masih berisi komponen tidak dihapus diam-diam: anaknya ikut
     * terhapus, dan itu jarang yang dimaksud.
     */
    private static function penjagaHapus(): callable
    {
        return function (ExhaustComponent $record): ?string {
            $isi = $record->children()->count();

            return $isi > 0
                ? "Bagian ini masih berisi {$isi} komponen. Pindahkan atau hapus komponennya dulu."
                : null;
        };
    }
}
