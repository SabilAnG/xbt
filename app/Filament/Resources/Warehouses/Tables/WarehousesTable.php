<?php

namespace App\Filament\Resources\Warehouses\Tables;

use App\Filament\Resources\Warehouses\WarehouseResource;
use App\Models\Warehouse;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Gudang ditampilkan sebagai kartu, bukan baris tabel.
 *
 * Jumlahnya sedikit dan jarang bertambah, jadi yang dicari orang bukan
 * "baris mana" melainkan "gudang mana" — dan kartu menjawab itu lebih cepat.
 * Seluruh kartu bisa diklik untuk membuka isinya.
 */
class WarehousesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->contentGrid(['default' => 1, 'md' => 2, 'xl' => 4])
            ->paginated(false)
            ->recordUrl(fn (Warehouse $record) => WarehouseResource::getUrl('isi', ['record' => $record]))
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->label('Gudang')
                        ->searchable()->sortable()
                        ->weight('bold')->size('lg'),

                    TextColumn::make('type')
                        ->badge()
                        ->formatStateUsing(fn (Warehouse $r) => $r->displayType())
                        ->color(fn (string $state) => match ($state) {
                            'bahan_mentah' => 'primary',
                            'setengah_jadi' => 'warning',
                            'finish_good' => 'success',
                            default => 'gray',
                        }),

                    TextColumn::make('description')
                        ->color('gray')->size('sm')->wrap(),

                    TextColumn::make('isi')
                        ->getStateUsing(fn (Warehouse $r) => self::ringkasIsi($r))
                        ->weight('medium'),
                ])->space(2),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(Warehouse::TYPES),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(self::penjagaHapus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(self::penjagaHapus())]),
            ])
            ->emptyStateHeading('Belum ada gudang')
            ->emptyStateIcon('heroicon-o-building-storefront');
    }

    /** "6 barang · Rp 4.320.000" — cukup untuk memilih kartu mana yang dibuka. */
    private static function ringkasIsi(Warehouse $gudang): string
    {
        $jumlah = $gudang->stocks()->where('qty', '!=', 0)->count();

        if ($jumlah === 0) {
            return 'Masih kosong';
        }

        return $jumlah.' barang · Rp '.number_format($gudang->stockValue(), 0, ',', '.');
    }

    /**
     * Gudang yang masih berisi tidak boleh hilang: stoknya ikut lenyap tanpa
     * jejak. Pindahkan atau nolkan isinya lewat opname dulu.
     */
    private static function penjagaHapus(): callable
    {
        return function (Warehouse $record): ?string {
            $berisi = $record->stocks()->where('qty', '!=', 0)->count();

            return $berisi > 0
                ? "Masih ada {$berisi} barang berstok di gudang ini. Nolkan atau pindahkan lewat stok opname dulu."
                : null;
        };
    }
}
