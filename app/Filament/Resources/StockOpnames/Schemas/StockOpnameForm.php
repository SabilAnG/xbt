<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Models\Item;
use App\Models\StockOpname;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sesi Opname')
                    ->columns(3)
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('No. Opname')
                            ->required()->unique(ignoreRecord: true)
                            ->default(fn () => DocumentNumber::next('stock_opnames'))
                            ->disabled(fn (?StockOpname $record) => $record?->isPosted())
                            ->dehydrated(),

                        DatePicker::make('opname_date')
                            ->label('Tanggal')->required()->default(now())
                            ->disabled(fn (?StockOpname $record) => $record?->isPosted()),

                        TextInput::make('counted_by')
                            ->label('Dihitung oleh')->maxLength(255)
                            ->disabled(fn (?StockOpname $record) => $record?->isPosted()),
                    ]),

                Section::make('Hasil Hitung Fisik')
                    ->description('Selisih dihitung otomatis: fisik − sistem. Hanya baris yang selisih yang akan mengoreksi stok.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah barang')
                            ->columns(4)
                            ->disabled(fn (?StockOpname $record) => $record?->isPosted())
                            ->schema([
                                Select::make('item_id')
                                    ->label('Barang')
                                    ->options(fn () => Item::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()->required()->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Bekukan stok sistem saat barang dipilih.
                                        $item = Item::find($state);
                                        $set('system_qty', $item ? (float) $item->stock : 0);
                                    }),

                                TextInput::make('system_qty')
                                    ->label('Stok Sistem')
                                    ->numeric()->required()->default(0)
                                    ->disabled()->dehydrated(),

                                TextInput::make('physical_qty')
                                    ->label('Hitung Fisik')
                                    ->numeric()->required()->default(0),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->collapsed()
                    ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
            ]);
    }
}
