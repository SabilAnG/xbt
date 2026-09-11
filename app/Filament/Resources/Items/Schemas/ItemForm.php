<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Barang')
                    ->columnSpanFull()
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
                    ->columnSpanFull()
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
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
            ]);
    }

    /**
     * Isian ringkas untuk mendaftarkan barang di tengah pekerjaan lain.
     *
     * Dipakai saat stok opname menemui barang yang belum ada di master —
     * menyuruh orang meninggalkan hitungan fisiknya, membuka menu Barang, lalu
     * kembali dan mengulang dari awal adalah cara paling ampuh membuat hasil
     * hitungan tidak pernah selesai dicatat.
     *
     * Stok sengaja tidak ada di sini: yang menentukan angkanya justru hitungan
     * fisik di opname itu sendiri.
     *
     * @return array<int, mixed>
     */
    public static function ringkas(): array
    {
        return [
            TextInput::make('name')
                ->label('Nama Barang')->required()->maxLength(255)
                ->helperText('Contoh: Knalpot Racing Beat Full System.'),

            TextInput::make('sku')
                ->label('Kode / SKU')->required()->maxLength(64)
                ->unique(table: 'items', column: 'sku'),

            Select::make('item_category_id')
                ->label('Kategori Produk')
                ->options(fn () => ItemCategory::query()
                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->createOptionForm(fn () => self::masterRingkas('Kategori Produk'))
                ->createOptionUsing(fn (array $data) => ItemCategory::create(
                    $data + ['slug' => Str::slug($data['name'])]
                )->getKey()),

            Select::make('item_type_id')
                ->label('Jenis Barang')
                ->options(fn () => ItemType::query()
                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->createOptionForm(fn () => self::masterRingkas('Jenis Barang'))
                ->createOptionUsing(fn (array $data) => ItemType::create(
                    $data + ['slug' => Str::slug($data['name'])]
                )->getKey()),

            // Bukan kolom milik barang — hanya penyaring, supaya daftar type
            // motor tidak perlu ditelusuri seluruhnya. Karena itu tidak ikut
            // disimpan.
            Select::make('brand_penyaring')
                ->label('Brand Motor')
                ->options(fn () => MotorcycleBrand::query()
                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()->live()->dehydrated(false)
                ->placeholder('Semua brand')
                ->helperText('Menyaring pilihan type motor di bawah.'),

            Select::make('motorcycleModels')
                ->label('Cocok untuk Type Motor')
                ->options(fn (callable $get) => MotorcycleModel::query()
                    ->where('is_active', true)
                    ->when($get('brand_penyaring'), fn ($q, $brand) => $q->where('motorcycle_brand_id', $brand))
                    ->with('brand')->get()
                    ->mapWithKeys(fn (MotorcycleModel $m) => [$m->id => $m->fullName()]))
                ->multiple()->searchable()
                ->columnSpanFull()
                ->createOptionForm(fn (callable $get) => self::typeMotorBaru($get('brand_penyaring')))
                ->createOptionUsing(fn (array $data) => MotorcycleModel::create($data)->getKey()),

            TextInput::make('unit')->label('Satuan')->default('pcs')->required()->maxLength(255),

            TextInput::make('cost_price')
                ->label('Harga Beli')->numeric()->default(0)->minValue(0)->prefix('Rp'),

            TextInput::make('sell_price')
                ->label('Harga Jual')->numeric()->default(0)->minValue(0)->prefix('Rp'),
        ];
    }

    /**
     * Simpan barang baru berikut type motornya.
     *
     * `motorcycleModels` relasi banyak-ke-banyak, jadi tidak bisa ikut
     * `create()` begitu saja — dipisah di sini supaya semua pemanggil melewati
     * jalan yang sama.
     *
     * @param  array<string, mixed>  $data
     */
    public static function buat(array $data): int
    {
        $typeMotor = Arr::pull($data, 'motorcycleModels', []);

        $barang = Item::create($data + ['stock' => 0, 'is_active' => true]);
        $barang->motorcycleModels()->sync($typeMotor);

        return $barang->getKey();
    }

    /** @return array<int, mixed> */
    private static function masterRingkas(string $label): array
    {
        return [
            TextInput::make('name')->label($label)->required()->maxLength(255),
        ];
    }

    /**
     * Type motor baru, brand-nya sudah terisi dari penyaring di atas.
     *
     * @return array<int, mixed>
     */
    private static function typeMotorBaru(mixed $brand): array
    {
        return [
            Select::make('motorcycle_brand_id')
                ->label('Brand')
                ->options(fn () => MotorcycleBrand::query()
                    ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()->required()->live()
                ->default($brand)
                ->createOptionForm([
                    TextInput::make('name')->label('Nama Brand')->required()->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data) => MotorcycleBrand::create(
                    $data + ['slug' => Str::slug($data['name'])]
                )->getKey()),

            TextInput::make('name')
                ->label('Type Motor')->required()->maxLength(255)
                ->helperText('Contoh: Vario 160, Beat Street.')
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                    if (blank($state)) {
                        return;
                    }

                    // Slug memuat brand agar "Beat" Honda dan Yamaha tidak bentrok.
                    $merek = MotorcycleBrand::find($get('motorcycle_brand_id'));
                    $set('slug', Str::slug(trim(($merek?->name ?? '').' '.$state)));
                }),

            TextInput::make('slug')
                ->required()->maxLength(255)
                ->unique(table: 'motorcycle_models', column: 'slug')
                ->helperText('Terisi otomatis dari brand + type.'),
        ];
    }
}
