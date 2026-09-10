<?php

namespace App\Filament\Resources\ProductionItemOpnames\Schemas;

use App\Models\ProductionItem;
use App\Models\ProductionItemOpname;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form stok opname barang produksi.
 *
 * Juga cara mengisi stok pertama kali: barang yang belum pernah punya stok
 * tercatat 0, jadi hitungan fisiknya langsung menjadi selisih dan masuk ke
 * kartu stok begitu dibukukan.
 */
class ProductionItemOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        $terkunci = fn (?ProductionItemOpname $record) => $record?->isPosted();

        return $schema->components([
            Section::make('Sesi Opname')
                ->description('Nota ini tidak menyentuh stok sampai ditekan Bukukan.')
                ->columns(3)
                ->schema([
                    TextInput::make('opname_number')
                        ->label('No. Opname')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('production_item_opnames'))
                        ->disabled($terkunci)->dehydrated(),

                    DatePicker::make('opname_date')
                        ->label('Tanggal')->required()->default(now())
                        ->disabled($terkunci),

                    TextInput::make('counted_by')
                        ->label('Dihitung oleh')->maxLength(255)
                        ->disabled($terkunci),
                ]),

            Section::make('Hasil Hitung Fisik')
                ->description('Selisih dihitung otomatis: fisik − catatan. Hanya baris yang selisih yang mengoreksi stok, dan koreksinya tercatat di kartu stok.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah barang')
                        ->columns(['default' => 1, 'md' => 8])
                        ->disabled($terkunci)
                        ->itemLabel(fn (array $state) => self::barisLabel($state))
                        ->schema([
                            Select::make('production_item_id')
                                ->label('Barang')
                                ->options(fn () => ProductionItem::query()
                                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()->distinct()
                                ->columnSpan(['default' => 1, 'md' => 3])
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Catatan sistem dibekukan saat barang dipilih,
                                    // supaya selisihnya tetap bercerita walau stok
                                    // bergerak sebelum notanya dibukukan.
                                    $barang = ProductionItem::find($state);
                                    $set('system_qty', $barang ? (float) $barang->stock : 0);
                                }),

                            TextInput::make('system_qty')
                                ->label('Catatan Sistem')
                                ->numeric()->required()->default(0)
                                ->disabled()->dehydrated()
                                ->suffix(fn (callable $get) => self::satuan($get('production_item_id')))
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            TextInput::make('physical_qty')
                                ->label('Hitung Fisik')
                                ->numeric()->required()->default(0)
                                ->suffix(fn (callable $get) => self::satuan($get('production_item_id')))
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->helperText(fn (callable $get) => self::petunjukSatuan($get('production_item_id'))),

                            TextInput::make('notes')
                                ->label('Catatan')->maxLength(255)
                                ->columnSpan(['default' => 1, 'md' => 1]),
                        ]),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    /** Satuan pakai barang yang dipilih, untuk imbuhan isian. */
    private static function satuan(mixed $itemId): ?string
    {
        return ProductionItem::find($itemId)?->baseUnit();
    }

    /** Pengingat konversi, supaya batang tidak tertukar dengan milimeter. */
    private static function petunjukSatuan(mixed $itemId): ?string
    {
        $barang = ProductionItem::find($itemId);

        if (! $barang || $barang->shape === 'count') {
            return null;
        }

        return 'Isi dalam '.$barang->baseUnit().' — '.$barang->conversionLabel().'.';
    }

    private static function barisLabel(array $state): string
    {
        $barang = ProductionItem::find($state['production_item_id'] ?? null);

        if (! $barang) {
            return 'Baris baru';
        }

        $selisih = (float) ($state['physical_qty'] ?? 0) - (float) ($state['system_qty'] ?? 0);

        return $barang->name.' — '.($selisih == 0.0
            ? 'cocok'
            : ($selisih > 0 ? 'lebih ' : 'kurang ').$barang->formatBase(abs($selisih)));
    }
}
