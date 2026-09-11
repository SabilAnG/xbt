<?php

namespace App\Filament\Resources\Items\Tables;

use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),

                TextColumn::make('name')->label('Nama')->searchable()->sortable()->wrap()
                    ->description(fn (Item $r) => collect([$r->category?->name, $r->type?->name])->filter()->implode(' • ') ?: null),

                TextColumn::make('stock')
                    ->label('Stok')
                    ->alignRight()
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(fn ($state, Item $r) => rtrim(rtrim((string) $state, '0'), '.').' '.$r->unit)
                    ->color(fn (Item $r) => (float) $r->stock <= 0
                        ? 'danger'
                        : ((float) $r->stock <= (float) $r->min_stock ? 'warning' : 'success')),

                TextColumn::make('cost_price')->label('Harga Beli')->money('IDR')->alignRight()->toggleable(),
                TextColumn::make('sell_price')->label('Harga Jual')->money('IDR')->alignRight()->sortable(),

                // Margin atas harga jual — bagian dari uang masuk yang benar
                // benar jadi laba. Merah berarti dijual di bawah modal.
                TextColumn::make('margin')->label('Margin')->alignRight()->badge()
                    ->getStateUsing(function (Item $record) {
                        $jual = (float) $record->sell_price;

                        return $jual > 0
                            ? number_format((($jual - (float) $record->cost_price) / $jual) * 100, 1, ',', '.').'%'
                            : null;
                    })
                    ->color(function (Item $record) {
                        $jual = (float) $record->sell_price;

                        if ($jual <= 0) {
                            return 'gray';
                        }

                        $margin = (($jual - (float) $record->cost_price) / $jual) * 100;

                        return match (true) {
                            $margin < 0 => 'danger',
                            $margin < 15 => 'warning',
                            default => 'success',
                        };
                    })
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('nilai')
                    ->label('Nilai Stok')
                    ->alignRight()
                    ->money('IDR')
                    ->getStateUsing(fn (Item $r) => $r->stockValue())
                    ->summarize(
                        Summarizer::make()
                            ->label('Total')
                            ->using(fn ($query) => $query->sum(DB::raw('stock * cost_price')))
                            ->money('IDR')
                    ),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('item_category_id')->label('Kategori')
                    ->relationship('category', 'name')->searchable()->preload(),
                SelectFilter::make('item_type_id')->label('Jenis')
                    ->relationship('type', 'name')->searchable()->preload(),
                Filter::make('menipis')
                    ->label('Stok menipis / habis')
                    ->query(fn (Builder $q) => $q->whereColumn('stock', '<=', 'min_stock')),
            ])
            ->recordActions([
                Action::make('kartu')
                    ->label('Kartu stok')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->color('gray')
                    ->url(fn (Item $record) => ItemResource::getUrl('kartu', ['record' => $record])),

                EditAction::make()->label('Ubah'),

                TableActions::delete(self::guard()),
            ])
            ->headerActions([
                TableExport::action('stok-barang', [
                    'SKU' => fn (Item $r) => $r->sku,
                    'Nama' => fn (Item $r) => $r->name,
                    'Kategori' => fn (Item $r) => $r->category?->name,
                    'Jenis' => fn (Item $r) => $r->type?->name,
                    'Satuan' => fn (Item $r) => $r->unit,
                    'Stok' => fn (Item $r) => (float) $r->stock,
                    'Stok Minimum' => fn (Item $r) => (float) $r->min_stock,
                    'Harga Beli' => fn (Item $r) => (float) $r->cost_price,
                    'Harga Jual' => fn (Item $r) => (float) $r->sell_price,
                    'Margin %' => fn (Item $r) => (float) $r->sell_price > 0
                        ? round((((float) $r->sell_price - (float) $r->cost_price) / (float) $r->sell_price) * 100, 2)
                        : null,
                    'Nilai Stok' => fn (Item $r) => $r->stockValue(),
                    'Aktif' => fn (Item $r) => $r->is_active ? 'Ya' : 'Tidak',
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(self::guard()),
                ]),
            ])
            ->emptyStateHeading('Belum ada barang')
            ->emptyStateDescription('Tambahkan barang dulu sebelum mencatat pembelian atau penjualan.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    /**
     * Barang yang sudah punya riwayat tidak boleh hilang — kartu stok dan nota
     * lama akan kehilangan acuannya.
     */
    private static function guard(): callable
    {
        return function (Item $item): ?string {
            if ($item->movements()->exists()) {
                return 'Barang ini sudah punya kartu stok. Nonaktifkan saja agar riwayatnya tetap utuh.';
            }

            $terpakai = DB::table('purchase_items')->where('item_id', $item->id)->count()
                + DB::table('sale_items')->where('item_id', $item->id)->count();

            if ($terpakai > 0) {
                return "Barang ini dipakai di {$terpakai} baris nota. Hapus notanya dulu, atau nonaktifkan barang ini.";
            }

            return null;
        };
    }
}
