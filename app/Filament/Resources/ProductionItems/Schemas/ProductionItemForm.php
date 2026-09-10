<?php

namespace App\Filament\Resources\ProductionItems\Schemas;

use App\Models\ProductionItem;
use App\Models\ProductionItemCategory;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Form master barang produksi.
 *
 * Ukuran yang muncul mengikuti bentuk yang dipilih: menanyakan lebar lembaran
 * untuk sepotong pipa hanya membingungkan. Angka konversi dan harga per satuan
 * pakai ditampilkan langsung supaya salah ketik ukuran ketahuan saat itu juga,
 * bukan setelah HPP terlanjur dipakai menetapkan harga jual.
 */
class ProductionItemForm
{
    /** Isian yang mengikuti satuan ukuran pilihan. */
    private const MEDAN_UKURAN = ['length_mm', 'width_mm', 'diameter_mm', 'thickness_mm'];

    public static function configure(Schema $schema): Schema
    {
        $bentuk = fn (string ...$tipe) => fn (callable $get) => in_array($get('shape'), $tipe, true);

        return $schema->components([
            Section::make('Identitas')
                ->description('Pilih jenisnya, dan peran serta cara pengadaannya ikut terisi sendiri — tinggal lanjut ke ukuran dan harga.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Barang')->required()->maxLength(255)
                        ->helperText('Contoh: Pipa SS 201 Ø28 x 1,2mm'),

                    TextInput::make('sku')
                        ->label('Kode / SKU')->required()->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->helperText('Contoh: PIPA-28, PLAT-08.'),

                    Select::make('production_item_category_id')
                        ->label('Jenis Barang')
                        ->relationship('category', 'name')
                        ->searchable()->preload()->live()
                        ->helperText('Pipa, Plat, Baut & Mur. Peran dan cara pengadaan mengikuti jenis yang dipilih.')
                        ->createOptionForm(fn () => self::jenisBaru())
                        ->createOptionUsing(fn (array $data) => ProductionItemCategory::create(
                            $data + ['slug' => Str::slug($data['name'])]
                        )->getKey())
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $jenis = ProductionItemCategory::find($state)) {
                                return;
                            }

                            $set('role', $jenis->role);
                            $set('source', $jenis->source);
                        }),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    // Ditampilkan supaya terlihat apa yang terisi, dan tetap
                    // bisa diubah: satu jenis jarang seragam seratus persen.
                    Select::make('role')
                        ->label('Perannya di produk')
                        ->options(ProductionItem::ROLES)
                        ->default('utama')->required()
                        ->helperText('Terisi dari jenis barang. Ubah hanya bila barang ini menyimpang dari jenisnya.'),

                    Select::make('source')
                        ->label('Didapat dari')
                        ->options(ProductionItem::SOURCES)
                        ->default('beli')->required()
                        ->helperText('Terisi dari jenis barang.'),
                ]),

            Section::make('Ukuran, Satuan & Harga')
                ->description('Anda membeli per batang atau lembar, tapi memakainya per milimeter. Isi ukurannya sekali di sini, sistem yang menghitung harga per satuan pakai.')
                ->columns(3)
                ->schema([
                    Select::make('shape')
                        ->label('Bentuk')
                        ->options(ProductionItem::SHAPES)
                        ->default('count')->required()->live()
                        ->helperText('Menentukan ukuran mana yang berlaku.'),

                    TextInput::make('unit')
                        ->label('Satuan Beli')->required()->default('pcs')->maxLength(255)
                        ->helperText('Cara Anda membelinya: batang, lembar, kg, tabung, pcs.'),

                    TextInput::make('cost_price')
                        ->label('Harga per Satuan Beli')
                        ->numeric()->prefix('Rp')->default(0)->required()->live(onBlur: true),

                    Select::make('size_unit')
                        ->label('Satuan ukuran')
                        ->options(ProductionItem::SIZE_UNIT_LABELS)
                        ->default('mm')->required()->live()
                        ->visible($bentuk('linear', 'sheet', 'count'))
                        ->helperText('Cara Anda menyebut ukurannya. Disimpan tetap dalam mm, jadi perhitungan tidak ikut berubah.')
                        ->afterStateUpdated(function ($state, $old, callable $get, callable $set) {
                            // Angka di layar dinyatakan ulang dalam satuan baru;
                            // ukuran fisiknya tetap sama. 6.000 mm jadi 600 cm,
                            // bukan tiba-tiba berarti 6.000 cm.
                            foreach (self::MEDAN_UKURAN as $medan) {
                                $nilai = $get($medan);

                                if ($nilai === null || $nilai === '') {
                                    continue;
                                }

                                $mm = ProductionItem::toMm((float) $nilai, $old);
                                $set($medan, self::rapikan(ProductionItem::fromMm($mm, $state)));
                            }
                        }),

                    TextInput::make('length_mm')
                        ->label(fn (callable $get) => $get('shape') === 'sheet' ? 'Panjang lembar' : 'Panjang per batang')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                        ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                        ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                        ->required($bentuk('linear', 'sheet'))
                        ->visible($bentuk('linear', 'sheet')),

                    TextInput::make('width_mm')
                        ->label('Lebar lembar')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                        ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                        ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                        ->required($bentuk('sheet'))
                        ->visible($bentuk('sheet')),

                    TextInput::make('weight_gram')
                        ->label('Berat per satuan beli (gram)')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->required($bentuk('weight'))
                        ->visible($bentuk('weight'))
                        ->helperText('1 kg = 1000.'),

                    TextInput::make('volume_ml')
                        ->label('Volume per satuan beli (ml)')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->required($bentuk('volume'))
                        ->visible($bentuk('volume'))
                        ->helperText('1 liter = 1000.'),

                    TextInput::make('diameter_mm')
                        ->label('Diameter')
                        ->numeric()->minValue(0)
                        ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                        ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                        ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                        ->visible($bentuk('linear', 'count'))
                        ->helperText('Keterangan ukuran, tidak ikut hitungan.'),

                    TextInput::make('thickness_mm')
                        ->label('Tebal')
                        ->numeric()->minValue(0)
                        ->suffix(fn (callable $get) => self::lambang($get('size_unit')))
                        ->formatStateUsing(fn ($state, callable $get) => self::keSatuan($state, $get('size_unit')))
                        ->dehydrateStateUsing(fn ($state, callable $get) => self::keMm($state, $get('size_unit')))
                        ->visible($bentuk('linear', 'sheet'))
                        ->helperText('Keterangan ukuran, tidak ikut hitungan.'),

                    Placeholder::make('konversi')
                        ->label('Hasil konversi')
                        ->columnSpanFull()
                        ->content(fn (callable $get) => self::previewKonversi($get)),
                ]),

            Section::make('Stok')
                ->columns(3)
                ->schema([
                    TextInput::make('stock')
                        ->label('Stok saat ini')
                        ->numeric()->default(0)
                        ->disabled()->dehydrated(false)
                        ->helperText('Dalam satuan pakai. Hanya berubah lewat pembelian, produksi, atau stok opname.'),

                    TextInput::make('min_stock')
                        ->label('Stok minimum')
                        ->numeric()->default(0)->minValue(0)
                        ->helperText('Dalam satuan pakai juga — pipa 12000 berarti 12 meter.'),

                    TextInput::make('min_reusable')
                        ->label('Sisa terkecil yang masih terpakai')
                        ->numeric()->default(0)->minValue(0)
                        ->helperText('Sisa potong di bawah angka ini dihitung sampah, di atasnya kembali jadi stok. Pipa 300 berarti potongan di bawah 30 cm dibuang. Isi 0 bila semua sisa masih terpakai.'),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }

    /**
     * Isian ringkas untuk membuat jenis barang tanpa meninggalkan layar ini.
     *
     * Peran dan sumber ikut ditanyakan karena itulah gunanya jenis: begitu
     * dipilih, keduanya langsung terisi ke barang yang sedang dibuat.
     *
     * @return array<int, mixed>
     */
    private static function jenisBaru(): array
    {
        return [
            TextInput::make('name')
                ->label('Nama Jenis')->required()->maxLength(255)
                ->helperText('Contoh: Pipa, Plat, Baut & Mur.'),

            Select::make('role')
                ->label('Perannya di produk')
                ->options(ProductionItem::ROLES)
                ->default('utama')->required(),

            Select::make('source')
                ->label('Didapat dari')
                ->options(ProductionItem::SOURCES)
                ->default('beli')->required(),
        ];
    }

    // ------------------------------------------------------- satuan ukuran

    /** Lambang singkat untuk imbuhan isian. */
    private static function lambang(?string $satuan): string
    {
        return $satuan === 'inch' ? '"' : ($satuan ?: 'mm');
    }

    /** mm yang tersimpan -> angka dalam satuan pilihan, untuk ditampilkan. */
    private static function keSatuan(mixed $state, ?string $satuan): mixed
    {
        return ($state === null || $state === '')
            ? $state
            : self::rapikan(ProductionItem::fromMm((float) $state, $satuan));
    }

    /** Angka dalam satuan pilihan -> mm, untuk disimpan. */
    private static function keMm(mixed $state, ?string $satuan): ?float
    {
        return ($state === null || $state === '')
            ? null
            : ProductionItem::toMm((float) $state, $satuan);
    }

    /**
     * Buang ekor pecahan yang muncul dari pembagian, tanpa mengorbankan
     * ketelitian inch: 1/2" = 12,7 mm harus tetap utuh.
     */
    private static function rapikan(float $n): float
    {
        return round($n, 4);
    }

    /**
     * Konversi dan harga turunannya, dihitung dari isian yang sedang diketik.
     * Salah ketik satu nol ketahuan di sini, bukan setelah HPP dipakai.
     */
    private static function previewKonversi(callable $get): string
    {
        $satuan = $get('size_unit');

        $barang = new ProductionItem([
            'shape' => $get('shape') ?: 'count',
            'unit' => $get('unit') ?: 'pcs',
            // Isian ada dalam satuan pilihan; hitungannya selalu mm.
            'length_mm' => ProductionItem::toMm((float) ($get('length_mm') ?: 0), $satuan),
            'width_mm' => ProductionItem::toMm((float) ($get('width_mm') ?: 0), $satuan),
            'weight_gram' => $get('weight_gram') ?: 0,
            'volume_ml' => $get('volume_ml') ?: 0,
            'cost_price' => $get('cost_price') ?: 0,
        ]);

        if ($barang->shape !== 'count' && $barang->basePerUnit() <= 1) {
            return 'Isi ukurannya dulu untuk melihat konversi.';
        }

        return $barang->conversionLabel().'  ·  '.$barang->displayBasePrice();
    }
}
