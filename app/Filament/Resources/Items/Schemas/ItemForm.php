<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Barang')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('Kode / SKU')
                            ->required()->unique(ignoreRecord: true)->maxLength(64),

                        TextInput::make('name')->label('Nama Barang')->required()->maxLength(255),

                        Select::make('item_category_id')
                            ->label('Kategori Produk')
                            ->relationship('category', 'name')
                            ->searchable()->preload()
                            ->helperText('Full set, silincer, leheran, dst.'),

                        Select::make('item_type_id')
                            ->label('Produk')
                            ->relationship('type', 'name')
                            ->searchable()->preload()
                            ->helperText('Racing, standar, standar racing, dst.'),

                        Select::make('motorcycleModels')
                            ->label('Cocok untuk type motor')
                            ->relationship('motorcycleModels', 'name')
                            ->multiple()->searchable()->preload()
                            ->columnSpanFull(),

                        Select::make('product_id')
                            ->label('Tautkan ke produk website')
                            ->relationship('product', 'name')
                            ->searchable()->preload()
                            ->columnSpanFull()
                            ->helperText('Opsional — hanya bila barang ini memang produk yang tampil di website.'),
                    ]),

                Section::make('Harga & Stok')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit')->label('Satuan')->default('pcs')->required(),

                        TextInput::make('cost_price')
                            ->label('Harga Beli')
                            ->numeric()->default(0)->minValue(0)->prefix('Rp')
                            ->helperText('Diperbarui otomatis setiap pembelian dibukukan.'),

                        TextInput::make('sell_price')
                            ->label('Harga Jual')
                            ->numeric()->default(0)->minValue(0)->prefix('Rp'),

                        TextInput::make('stock')
                            ->label('Stok saat ini')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Hanya bisa berubah lewat pembelian, penjualan, atau stok opname.'),

                        TextInput::make('min_stock')
                            ->label('Stok minimum')
                            ->numeric()->default(0)->minValue(0)
                            ->helperText('Dipakai untuk menandai stok menipis.'),

                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ]),

                Section::make('Catatan')
                    ->collapsed()
                    ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
            ]);
    }
}
