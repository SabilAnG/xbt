<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('images.0.path')
                    ->label('')
                    ->disk('site')
                    ->square(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn ($record) => $record->fitment),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('images_count')
                    ->counts('images')
                    ->label('Images')
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Visible on the site'),
            ])
            ->recordActions([
                Action::make('lihat')
                    ->label('Lihat di situs')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn ($record) => url('/products/'.$record->slug))
                    ->openUrlInNewTab(),

                EditAction::make()->label('Ubah'),

                DeleteAction::make()
                    ->label('Hapus')
                    ->modalDescription('Produk dan seluruh gambarnya akan dihapus. Barang gudang yang tertaut tidak ikut terhapus, hanya tautannya yang dilepas.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus terpilih'),
                ]),
            ])
            ->emptyStateHeading('Belum ada produk')
            ->emptyStateDescription('Produk di sini yang tampil pada halaman /products situs.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
