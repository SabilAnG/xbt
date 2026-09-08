<?php

namespace App\Filament\Resources\MaterialPurchases\Schemas;

use App\Models\Material;
use App\Models\MaterialPurchase;
use App\Models\Rack;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaterialPurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        $terkunci = fn (?MaterialPurchase $record) => $record?->isPosted();

        return $schema->components([
            Section::make('Nota Pembelian Bahan')
                ->columns(3)
                ->schema([
                    TextInput::make('invoice_number')
                        ->label('No. Nota')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('material_purchases'))
                        ->disabled($terkunci)->dehydrated(),

                    DatePicker::make('purchased_at')
                        ->label('Tanggal')->required()->default(now())
                        ->disabled($terkunci),

                    Select::make('vendor_id')
                        ->label('Vendor')
                        ->relationship('vendor', 'name')
                        ->searchable()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nama Vendor')->required(),
                            TextInput::make('phone')->label('Telepon'),
                        ])
                        ->disabled($terkunci),

                    Select::make('wallet_id')
                        ->label('Dibayar dari dompet')
                        ->relationship('wallet', 'name')
                        ->searchable()->preload()
                        ->helperText('Kosongkan bila belum dibayar — kas tidak bergerak.')
                        ->disabled($terkunci),

                    TextInput::make('discount')->label('Diskon')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')->disabled($terkunci),

                    TextInput::make('shipping_cost')->label('Ongkir')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')->disabled($terkunci),
                ]),

            Section::make('Bahan yang Dibeli')
                ->description('Pilih rak tujuan agar stok tercatat sampai lokasinya.')
                ->schema([
                    Repeater::make('items')
                        ->relationship()
                        ->hiddenLabel()
                        ->addActionLabel('Tambah bahan')
                        ->columns(5)
                        ->disabled($terkunci)
                        ->schema([
                            Select::make('material_id')
                                ->label('Bahan')
                                ->options(fn () => Material::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()->required()->columnSpan(2)
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Harga beli terakhir sebagai titik awal.
                                    if ($m = Material::find($state)) {
                                        $set('unit_cost', (float) $m->cost_price);
                                    }
                                }),

                            Select::make('rack_id')
                                ->label('Rak tujuan')
                                ->options(fn () => Rack::with('warehouse')->where('is_active', true)->get()
                                    ->mapWithKeys(fn (Rack $r) => [$r->id => $r->fullName()]))
                                ->searchable(),

                            TextInput::make('qty')->label('Qty')
                                ->numeric()->required()->default(1)->minValue(0.001),

                            TextInput::make('unit_cost')->label('Harga Satuan')
                                ->numeric()->required()->default(0)->minValue(0)->prefix('Rp'),
                        ]),
                ]),

            Section::make('Catatan')
                ->collapsed()
                ->schema([Textarea::make('notes')->hiddenLabel()->rows(3)]),
        ]);
    }
}
