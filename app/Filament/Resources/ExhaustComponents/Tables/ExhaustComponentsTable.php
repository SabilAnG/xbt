<?php

namespace App\Filament\Resources\ExhaustComponents\Tables;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use App\Models\ExhaustComponent;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daftar bagian knalpot sebagai kartu.
 *
 * Menampilkan enam belas komponen sekaligus membuat orang membaca daftar,
 * bukan memahami susunannya. Yang tampil di sini hanya bagiannya — Header,
 * Silincer — dan isinya dibuka dengan mengklik kartunya, sama seperti gudang.
 */
class ExhaustComponentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->bagian()->withCount('children'))
            ->defaultSort('sort_order')
            ->contentGrid(['default' => 1, 'md' => 2, 'xl' => 3])
            ->paginated(false)
            ->recordUrl(fn (ExhaustComponent $record) => ExhaustComponentResource::getUrl('isi', ['record' => $record]))
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->label('Bagian')
                        ->searchable()->sortable()
                        ->weight('bold')->size('lg'),

                    TextColumn::make('notes')
                        ->color('gray')->size('sm')->wrap(),

                    TextColumn::make('isi')
                        ->getStateUsing(fn (ExhaustComponent $r) => self::ringkasIsi($r))
                        ->weight('medium'),

                    TextColumn::make('belum')
                        ->getStateUsing(fn (ExhaustComponent $r) => self::ringkasBelum($r))
                        ->color('warning')->size('sm'),
                ])->space(2),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(self::penjagaHapus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(self::penjagaHapus())]),
            ])
            ->emptyStateHeading('Belum ada bagian knalpot')
            ->emptyStateDescription('Mulai dari bagiannya — Header, Silincer — lalu isi komponennya.')
            ->emptyStateIcon('heroicon-o-puzzle-piece');
    }

    private static function ringkasIsi(ExhaustComponent $bagian): string
    {
        $jumlah = $bagian->children_count ?? $bagian->children()->count();

        return $jumlah === 0 ? 'Belum ada komponen' : $jumlah.' komponen';
    }

    /** Sisa pekerjaan yang paling sering dicari: bahan yang belum ditentukan. */
    private static function ringkasBelum(ExhaustComponent $bagian): ?string
    {
        $belum = $bagian->children()->whereNull('production_item_id')->count();

        return $belum > 0 ? $belum.' belum ada bahan bakunya' : null;
    }

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
