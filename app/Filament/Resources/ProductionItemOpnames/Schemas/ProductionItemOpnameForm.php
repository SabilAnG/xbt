<?php

namespace App\Filament\Resources\ProductionItemOpnames\Schemas;

use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Models\ProductionItem;
use App\Models\ProductionItemCategory;
use App\Models\ProductionItemOpname;
use App\Models\Warehouse;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form stok opname barang produksi.
 *
 * Hitung fisik diisi SEBAGAIMANA ORANG MENGHITUNGNYA — "4 batang utuh, sisa
 * 3 meter" — bukan dalam milimeter. Menyuruh orang mengalikan 4 x 6.000 + 3.000
 * sendiri berarti memindahkan pekerjaan sistem ke tangannya, dan di situlah
 * salah hitung lahir.
 *
 * Juga cara mengisi stok pertama kali: barang yang belum pernah punya stok
 * tercatat 0, jadi hitungan fisiknya langsung menjadi stok awal.
 */
class ProductionItemOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        $terkunci = fn (?ProductionItemOpname $record) => $record?->isPosted();

        return $schema->components([
            Section::make('Sesi Opname')
                ->description('Nota ini tidak menyentuh stok sampai ditekan Bukukan.')
                ->columns(4)
                ->schema([
                    TextInput::make('opname_number')
                        ->label('No. Opname')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('production_item_opnames'))
                        ->disabled($terkunci)->dehydrated(),

                    DatePicker::make('opname_date')
                        ->label('Tanggal')->required()->default(now())
                        ->disabled($terkunci),

                    // Satu sesi menghitung satu gudang. Kalau dicampur,
                    // "catatan sistem" jadi ambigu.
                    Select::make('warehouse_id')
                        ->label('Gudang yang dihitung')
                        ->options(fn () => Warehouse::query()
                            ->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                        ->required()->searchable()->live()
                        ->default(fn () => Warehouse::where('type', 'bahan_mentah')->value('id'))
                        ->disabled($terkunci)
                        ->helperText('Catatan sistem tiap baris diambil dari stok di gudang ini.')
                        ->afterStateUpdated(fn (callable $set) => $set('items', [])),

                    TextInput::make('counted_by')
                        ->label('Dihitung oleh')->maxLength(255)
                        ->disabled($terkunci),
                ]),

            Section::make('Hasil Hitung Fisik')
                ->description('Isi apa adanya sesuai cara Anda menghitung — berapa batang atau lembar yang utuh, lalu sisanya. Selisih dihitung otomatis, dan hanya baris yang selisih yang mengoreksi stok.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah barang')
                        ->columns(['default' => 1, 'md' => 12])
                        ->disabled($terkunci)
                        ->itemLabel(fn (array $state) => self::barisLabel($state))
                        ->schema([
                            // Jenis dipilih dulu supaya daftar barangnya pendek.
                            // Tidak disimpan — ia hanya alat menyaring.
                            Select::make('jenis_id')
                                ->label('Jenis')
                                ->options(fn () => ProductionItemCategory::query()
                                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->live()->dehydrated(false)
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->afterStateHydrated(function ($state, callable $get, callable $set) {
                                    if ($state === null && $barang = ProductionItem::find($get('production_item_id'))) {
                                        $set('jenis_id', $barang->production_item_category_id);
                                    }
                                })
                                ->afterStateUpdated(fn (callable $set) => $set('production_item_id', null)),

                            Select::make('production_item_id')
                                ->label('Barang')
                                ->options(fn (callable $get) => ProductionItem::query()
                                    ->where('is_active', true)
                                    ->when($get('jenis_id'), fn ($q, $id) => $q->where('production_item_category_id', $id))
                                    ->orderBy('name')->pluck('name', 'id'))
                                ->searchable()->required()->distinct()
                                ->columnSpan(['default' => 1, 'md' => 3])
                                ->live()
                                ->createOptionForm(fn () => ProductionItemForm::ringkas())
                                ->createOptionUsing(function (array $data, callable $set) {
                                    $barang = ProductionItem::create($data);

                                    $set('jenis_id', $barang->production_item_category_id);
                                    $set('system_qty', 0);

                                    return $barang->getKey();
                                })
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    // Catatan sistem dibekukan saat barang dipilih,
                                    // supaya selisihnya tetap bercerita walau stok
                                    // bergerak sebelum notanya dibukukan. Yang dibaca
                                    // stok DI GUDANG SESI INI, bukan totalnya.
                                    $barang = ProductionItem::find($state);
                                    $gudang = (int) $get('../../warehouse_id');

                                    $set('system_qty', $barang && $gudang ? $barang->stockIn($gudang) : 0);
                                }),

                            TextInput::make('system_qty')
                                ->label('Catatan Sistem')
                                ->numeric()->required()->default(0)
                                ->disabled()->dehydrated()
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->helperText(fn (callable $get) => self::bacaanSistem($get)),

                            TextInput::make('count_whole')
                                ->label('Utuh')
                                ->numeric()->minValue(0)->default(0)->live(onBlur: true)
                                ->suffix(fn (callable $get) => self::satuanBeli($get('production_item_id')))
                                ->columnSpan(['default' => 1, 'md' => 2]),

                            TextInput::make('count_remainder')
                                ->label(fn (callable $get) => self::labelSisa($get('production_item_id')))
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'md' => 1])
                                ->visible(fn (callable $get) => self::punyaSisa($get('production_item_id'))),

                            TextInput::make('count_remainder_width')
                                ->label('x Lebar (mm)')
                                ->numeric()->minValue(0)->live(onBlur: true)
                                ->columnSpan(['default' => 1, 'md' => 1])
                                ->visible(fn (callable $get) => self::bentuk($get('production_item_id')) === 'sheet'),

                            Placeholder::make('hasil')
                                ->label('Hitung fisik')
                                ->columnSpan(['default' => 1, 'md' => 2])
                                ->content(fn (callable $get) => self::hasilHitung($get)),

                            TextInput::make('notes')
                                ->label('Catatan')->maxLength(255)
                                ->columnSpan(['default' => 1, 'md' => 12]),
                        ]),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    // ------------------------------------------------------------- pembantu

    private static function barang(mixed $itemId): ?ProductionItem
    {
        return $itemId ? ProductionItem::find($itemId) : null;
    }

    private static function bentuk(mixed $itemId): ?string
    {
        return self::barang($itemId)?->shape;
    }

    private static function satuanBeli(mixed $itemId): ?string
    {
        return self::barang($itemId)?->unit;
    }

    private static function punyaSisa(mixed $itemId): bool
    {
        $barang = self::barang($itemId);

        // Baut tidak punya "setengah baut"; lembaran sisanya berupa potongan
        // berukuran, jadi diisi panjang kali lebar.
        return $barang !== null && $barang->shape !== 'count';
    }

    private static function labelSisa(mixed $itemId): string
    {
        $barang = self::barang($itemId);

        if (! $barang) {
            return 'Sisa';
        }

        if ($barang->shape === 'sheet') {
            return 'Sisa: Panjang (mm)';
        }

        return 'Sisa ('.($barang->remainderUnit()[0] ?? '').')';
    }

    /** Catatan sistem dalam bentuk yang enak dibaca, plus setaranya. */
    private static function bacaanSistem(callable $get): ?string
    {
        $barang = self::barang($get('production_item_id'));

        if (! $barang) {
            return null;
        }

        $qty = (float) $get('system_qty');
        $bacaan = $barang->formatBase($qty);

        if ($barang->shape === 'count' || $barang->basePerUnit() <= 1) {
            return $bacaan;
        }

        $trim = fn ($n) => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');

        return $bacaan.'  ≈ '.$trim($barang->toPurchase($qty)).' '.$barang->unit;
    }

    /** Hasil hitungnya, supaya salah ketik ketahuan sebelum disimpan. */
    private static function hasilHitung(callable $get): string
    {
        $barang = self::barang($get('production_item_id'));

        if (! $barang) {
            return 'Pilih barangnya dulu.';
        }

        $total = $barang->fromCount(
            (float) $get('count_whole'),
            $get('count_remainder') !== null && $get('count_remainder') !== '' ? (float) $get('count_remainder') : null,
            $get('count_remainder_width') !== null && $get('count_remainder_width') !== '' ? (float) $get('count_remainder_width') : null,
        );

        $selisih = $total - (float) $get('system_qty');
        $arah = match (true) {
            abs($selisih) < 0.0001 => 'cocok',
            $selisih > 0 => 'lebih '.$barang->formatBase($selisih),
            default => 'kurang '.$barang->formatBase(abs($selisih)),
        };

        return $barang->formatBase($total).'  ·  '.$arah;
    }

    private static function barisLabel(array $state): string
    {
        $barang = self::barang($state['production_item_id'] ?? null);

        if (! $barang) {
            return 'Baris baru';
        }

        $total = $barang->fromCount(
            (float) ($state['count_whole'] ?? 0),
            $state['count_remainder'] ?? null,
            $state['count_remainder_width'] ?? null,
        );

        $rincian = $barang->countLabel(
            $state['count_whole'] ?? null,
            $state['count_remainder'] ?? null,
            $state['count_remainder_width'] ?? null,
        );

        $selisih = $total - (float) ($state['system_qty'] ?? 0);

        return $barang->name.' — '.$rincian.' ('.($selisih == 0.0
            ? 'cocok'
            : ($selisih > 0 ? 'lebih ' : 'kurang ').$barang->formatBase(abs($selisih))).')';
    }
}
