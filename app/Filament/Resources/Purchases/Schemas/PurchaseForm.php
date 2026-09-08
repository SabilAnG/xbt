<?php

namespace App\Filament\Resources\Purchases\Schemas;

use App\Models\Item;
use App\Models\Purchase;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nota Pembelian')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('No. Nota')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => DocumentNumber::next('purchases'))
                            ->disabled(fn (?Purchase $record) => $record?->isPosted())
                            ->dehydrated(),

                        DatePicker::make('purchased_at')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->disabled(fn (?Purchase $record) => $record?->isPosted()),

                        TextInput::make('supplier_name')
                            ->label('Supplier')
                            ->maxLength(255)
                            ->disabled(fn (?Purchase $record) => $record?->isPosted()),

                        Select::make('wallet_id')
                            ->label('Dibayar dari dompet')
                            ->relationship('wallet', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Kosongkan bila belum dibayar — kas tidak akan bergerak.')
                            ->disabled(fn (?Purchase $record) => $record?->isPosted()),

                        TextInput::make('discount')
                            ->label('Diskon')
                            ->numeric()->default(0)->minValue(0)->prefix('Rp')
                            ->disabled(fn (?Purchase $record) => $record?->isPosted()),

                        TextInput::make('shipping_cost')
                            ->label('Ongkir')
                            ->numeric()->default(0)->minValue(0)->prefix('Rp')
                            ->disabled(fn (?Purchase $record) => $record?->isPosted()),
                    ]),

                Section::make('Barang')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah barang')
                            ->columns(4)
                            ->disabled(fn (?Purchase $record) => $record?->isPosted())
                            ->schema([
                                Select::make('item_id')
                                    ->label('Barang')
                                    ->options(fn () => Item::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Harga beli terakhir sebagai titik awal.
                                        $item = Item::find($state);
                                        if ($item) {
                                            $set('unit_cost', (float) $item->cost_price);
                                        }
                                    }),

                                TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()->required()->default(1)->minValue(0.01),

                                TextInput::make('unit_cost')
                                    ->label('Harga Beli')
                                    ->numeric()->required()->default(0)->minValue(0)->prefix('Rp'),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')->hiddenLabel()->rows(3),
                    ]),
            ]);
    }
}
