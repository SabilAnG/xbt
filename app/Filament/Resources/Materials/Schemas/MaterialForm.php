<?php

namespace App\Filament\Resources\Materials\Schemas;

use App\Models\Material;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaterialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Bahan')
                ->columns(2)
                ->schema([
                    TextInput::make('sku')->label('Kode / SKU')->required()
                        ->unique(ignoreRecord: true)->maxLength(64),

                    TextInput::make('name')->label('Nama Bahan')->required()->maxLength(255)
                        ->helperText('Contoh: Pipa SS 201 Ø28 x 1,2mm'),

                    Select::make('material_category_id')
                        ->label('Kategori')
                        ->relationship('category', 'name')
                        ->searchable()->preload(),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),

            Section::make('Satuan & Konversi')
                ->description('Anda membeli per batang atau lembar, tapi formula memakainya per milimeter atau mm². Isi dimensinya sekali di sini, sistem yang menghitung harga per satuan pakai.')
                ->columns(3)
                ->schema([
                    Select::make('dimension_type')
                        ->label('Tipe Bahan')
                        ->options(Material::DIMENSION_TYPES)
                        ->default('count')
                        ->required()
                        ->live()
                        ->helperText('Menentukan satuan yang dipakai formula.'),

                    TextInput::make('unit')
                        ->label('Satuan Beli')
                        ->default('pcs')
                        ->required()
                        ->helperText('Cara Anda belanja: batang, lembar, kg, tabung, pcs.'),

                    TextInput::make('cost_price')
                        ->label('Harga per Satuan Beli')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')
                        ->live(onBlur: true)
                        ->helperText('Diperbarui otomatis tiap pembelian bahan dibukukan.'),

                    // --- linear: pipa ---
                    TextInput::make('length_mm')
                        ->label('Panjang per batang (mm)')
                        ->numeric()->minValue(1)
                        ->live(onBlur: true)
                        ->visible(fn ($get) => $get('dimension_type') === 'linear')
                        ->required(fn ($get) => $get('dimension_type') === 'linear')
                        ->helperText('Pipa 6 meter → 6000'),

                    TextInput::make('diameter_mm')
                        ->label('Diameter (mm)')
                        ->numeric()
                        ->visible(fn ($get) => in_array($get('dimension_type'), ['linear', 'count'], true))
                        ->helperText('Untuk identifikasi, tidak dipakai hitungan.'),

                    // --- sheet: plat ---
                    TextInput::make('sheet_length_mm')
                        ->label('Panjang lembar (mm)')
                        ->numeric()->minValue(1)
                        ->live(onBlur: true)
                        ->visible(fn ($get) => $get('dimension_type') === 'sheet')
                        ->required(fn ($get) => $get('dimension_type') === 'sheet')
                        ->helperText('Plat 1200 x 2400 → 2400'),

                    TextInput::make('sheet_width_mm')
                        ->label('Lebar lembar (mm)')
                        ->numeric()->minValue(1)
                        ->live(onBlur: true)
                        ->visible(fn ($get) => $get('dimension_type') === 'sheet')
                        ->required(fn ($get) => $get('dimension_type') === 'sheet')
                        ->helperText('→ 1200'),

                    // --- weight: curah ---
                    TextInput::make('weight_gram')
                        ->label('Berat per satuan beli (gram)')
                        ->numeric()->minValue(1)
                        ->live(onBlur: true)
                        ->visible(fn ($get) => $get('dimension_type') === 'weight')
                        ->required(fn ($get) => $get('dimension_type') === 'weight')
                        ->helperText('1 kg → 1000'),

                    // --- volume: gas ---
                    TextInput::make('volume_ml')
                        ->label('Volume per satuan beli (ml)')
                        ->numeric()->minValue(1)
                        ->live(onBlur: true)
                        ->visible(fn ($get) => $get('dimension_type') === 'volume')
                        ->required(fn ($get) => $get('dimension_type') === 'volume')
                        ->helperText('Tabung 10.000 liter → 10000000'),

                    TextInput::make('thickness_mm')
                        ->label('Tebal (mm)')
                        ->numeric()
                        ->visible(fn ($get) => in_array($get('dimension_type'), ['linear', 'sheet'], true))
                        ->helperText('Untuk identifikasi.'),

                    // Hasil konversi ditampilkan langsung supaya salah isi
                    // ketahuan sebelum disimpan.
                    Placeholder::make('konversi')
                        ->label('Hasil konversi')
                        ->columnSpanFull()
                        ->content(function ($get) {
                            $m = new Material([
                                'dimension_type' => $get('dimension_type') ?: 'count',
                                'unit' => $get('unit') ?: 'pcs',
                                'cost_price' => (float) ($get('cost_price') ?: 0),
                                'length_mm' => $get('length_mm'),
                                'sheet_length_mm' => $get('sheet_length_mm'),
                                'sheet_width_mm' => $get('sheet_width_mm'),
                                'weight_gram' => $get('weight_gram'),
                                'volume_ml' => $get('volume_ml'),
                            ]);

                            return $m->conversionLabel().'  —  '.$m->displayBasePrice();
                        }),
                ]),

            Section::make('Stok')
                ->columns(2)
                ->schema([
                    TextInput::make('stock')
                        ->label('Stok saat ini')
                        ->numeric()->disabled()->dehydrated(false)
                        ->helperText('Dalam satuan pakai. Hanya berubah lewat pembelian bahan, produksi, atau stok opname.'),

                    TextInput::make('min_stock')
                        ->label('Stok minimum')
                        ->numeric()->default(0)->minValue(0)
                        ->helperText('Dalam satuan pakai juga — mis. pipa 12000 berarti 12 meter.'),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }
}
