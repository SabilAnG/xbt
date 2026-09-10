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

                    TextInput::make('length_mm')
                        ->label(fn (callable $get) => $get('shape') === 'sheet' ? 'Panjang lembar (mm)' : 'Panjang per batang (mm)')
                        ->numeric()->minValue(0)->live(onBlur: true)
                        ->required($bentuk('linear', 'sheet'))
                        ->visible($bentuk('linear', 'sheet')),

                    TextInput::make('width_mm')
                        ->label('Lebar lembar (mm)')
                        ->numeric()->minValue(0)->live(onBlur: true)
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
                        ->label('Diameter (mm)')
                        ->numeric()->minValue(0)
                        ->visible($bentuk('linear', 'count'))
                        ->helperText('Keterangan ukuran, tidak ikut hitungan.'),

                    TextInput::make('thickness_mm')
                        ->label('Tebal (mm)')
                        ->numeric()->minValue(0)
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

    /**
     * Konversi dan harga turunannya, dihitung dari isian yang sedang diketik.
     * Salah ketik satu nol ketahuan di sini, bukan setelah HPP dipakai.
     */
    private static function previewKonversi(callable $get): string
    {
        $barang = new ProductionItem([
            'shape' => $get('shape') ?: 'count',
            'unit' => $get('unit') ?: 'pcs',
            'length_mm' => $get('length_mm') ?: 0,
            'width_mm' => $get('width_mm') ?: 0,
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
