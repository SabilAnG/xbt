<?php

namespace App\Filament\Resources\StockOpnames\Schemas;

use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Models\Item;
use App\Models\StockOpname;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;

class StockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sesi Opname')
                    ->columnSpanFull()
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

                // Selebar halaman: form resource bawaannya dua kolom, dan di
                // lebar segitu tabel barisnya jatuh ke tampilan bertumpuk.
                Section::make('Hasil Hitung Fisik')
                    ->columnSpanFull()
                    ->description('Selisih dihitung otomatis: fisik − sistem. Hanya baris yang selisih yang akan mengoreksi stok.')
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->addActionLabel('Tambah barang')
                            ->disabled(fn (?StockOpname $record) => $record?->isPosted())
                            ->table([
                                TableColumn::make('Barang')->width('34%')->markAsRequired(),
                                TableColumn::make('Stok Sistem')->width('14%')->alignment(Alignment::End),
                                TableColumn::make('Hitung Fisik')->width('14%')->alignment(Alignment::End),
                                TableColumn::make('Selisih')->width('12%')->alignment(Alignment::End),
                                TableColumn::make('Harga Jual')->width('26%')->alignment(Alignment::End),
                            ])
                            ->schema([
                                Select::make('item_id')
                                    ->hiddenLabel()
                                    ->options(fn () => Item::query()->where('is_active', true)->pluck('name', 'id'))
                                    ->searchable()->required()->distinct()
                                    ->selectablePlaceholder(false)
                                    ->placeholder('Pilih barang')
                                    ->live()
                                    // Barang yang ketemu di rak tapi belum ada
                                    // di master bisa didaftarkan di tempat,
                                    // berikut type motornya. Menyuruh orang
                                    // meninggalkan hitungannya untuk membuka
                                    // menu lain adalah cara paling ampuh
                                    // membuat hitungan itu tidak pernah selesai.
                                    ->createOptionForm(fn () => ItemForm::ringkas())
                                    ->createOptionUsing(fn (array $data) => ItemForm::buat($data))
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Bekukan stok sistem saat barang dipilih.
                                        // Barang yang baru didaftarkan bernilai
                                        // nol, dan hitungan fisiklah yang mengisinya.
                                        $item = Item::find($state);
                                        $set('system_qty', $item ? (float) $item->stock : 0);
                                        $set('unit_price', $item ? (float) $item->sell_price : null);
                                    }),

                                TextInput::make('system_qty')
                                    ->hiddenLabel()
                                    ->numeric()->required()->default(0)
                                    ->disabled()->dehydrated(),

                                TextInput::make('physical_qty')
                                    ->hiddenLabel()
                                    ->numeric()->required()->default(0)
                                    ->live(onBlur: true),

                                Placeholder::make('selisih')
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Medium)
                                    ->content(fn (callable $get) => self::selisih($get))
                                    ->color(fn (callable $get) => match (true) {
                                        self::angkaSelisih($get) > 0 => 'success',
                                        self::angkaSelisih($get) < 0 => 'danger',
                                        default => 'gray',
                                    }),

                                // Orang yang sedang memegang barangnya adalah
                                // orang yang paling tahu harganya sekarang.
                                // Dikosongkan berarti harga lama dibiarkan.
                                TextInput::make('unit_price')
                                    ->hiddenLabel()
                                    ->numeric()->minValue(0)->prefix('Rp')
                                    ->placeholder('biarkan'),
                            ]),
                    ]),

                Section::make('Catatan')
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
            ]);
    }

    private static function angkaSelisih(callable $get): float
    {
        return (float) ($get('physical_qty') ?: 0) - (float) ($get('system_qty') ?: 0);
    }

    /** "+3" atau "−2" — bertanda, supaya arahnya terbaca sekilas. */
    private static function selisih(callable $get): string
    {
        $selisih = self::angkaSelisih($get);

        if (abs($selisih) < 0.0001) {
            return 'cocok';
        }

        $angka = rtrim(rtrim(number_format(abs($selisih), 2, ',', '.'), '0'), ',');

        return ($selisih > 0 ? '+' : '−').$angka;
    }
}
