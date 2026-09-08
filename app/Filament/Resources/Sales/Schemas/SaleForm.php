<?php

namespace App\Filament\Resources\Sales\Schemas;

use App\Models\Item;
use App\Models\Sale;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Nota Penjualan')
                    ->columns(3)
                    ->schema([
                        TextInput::make('invoice_number')
                            ->label('No. Nota')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->default(fn () => DocumentNumber::next('sales'))
                            ->disabled(fn (?Sale $record) => $record?->isPosted())
                            ->dehydrated(),

                        DatePicker::make('sold_at')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->disabled(fn (?Sale $record) => $record?->isPosted()),

                        Select::make('wallet_id')
                            ->label('Uang masuk ke dompet')
                            ->relationship('wallet', 'name')
                            ->searchable()->preload()
                            ->helperText('Kosongkan bila belum dibayar.')
                            ->disabled(fn (?Sale $record) => $record?->isPosted()),

                        TextInput::make('customer_name')->label('Pembeli')->maxLength(255)
                            ->disabled(fn (?Sale $record) => $record?->isPosted()),
                        TextInput::make('customer_phone')->label('No. HP')->tel()->maxLength(64)
                            ->disabled(fn (?Sale $record) => $record?->isPosted()),

                        TextInput::make('discount')->label('Diskon')
                            ->numeric()->default(0)->minValue(0)->prefix('Rp')
                            ->disabled(fn (?Sale $record) => $record?->isPosted()),
                    ]),

                Section::make('Barang')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah barang')
                            ->columns(4)
                            ->disabled(fn (?Sale $record) => $record?->isPosted())
                            ->schema([
                                Select::make('item_id')
                                    ->label('Barang')
                                    ->options(fn () => Item::query()
                                        ->where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(fn (Item $i) => [
                                            $i->id => sprintf('%s (stok %s)', $i->name, rtrim(rtrim((string) $i->stock, '0'), '.')),
                                        ]))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $item = Item::find($state);
                                        if ($item) {
                                            $set('unit_price', (float) $item->sell_price);
                                        }
                                    }),

                                TextInput::make('qty')->label('Qty')
                                    ->numeric()->required()->default(1)->minValue(0.01),

                                TextInput::make('unit_price')->label('Harga Jual')
                                    ->numeric()->required()->default(0)->minValue(0)->prefix('Rp'),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->collapsed()
                    ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
            ]);
    }
}
