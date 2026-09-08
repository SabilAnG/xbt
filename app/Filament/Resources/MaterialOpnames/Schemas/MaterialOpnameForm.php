<?php

namespace App\Filament\Resources\MaterialOpnames\Schemas;

use App\Models\Material;
use App\Models\MaterialOpname;
use App\Models\MaterialStock;
use App\Models\Rack;
use App\Services\DocumentNumber;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaterialOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        $terkunci = fn (?MaterialOpname $record) => $record?->isPosted();

        return $schema->components([
            Section::make('Sesi Opname Bahan')
                ->columns(3)
                ->schema([
                    TextInput::make('opname_number')
                        ->label('No. Opname')
                        ->required()->unique(ignoreRecord: true)
                        ->default(fn () => DocumentNumber::next('material_opnames'))
                        ->disabled($terkunci)->dehydrated(),

                    DatePicker::make('opname_date')
                        ->label('Tanggal')->required()->default(now())->disabled($terkunci),

                    Select::make('warehouse_id')
                        ->label('Gudang')
                        ->relationship('warehouse', 'name')
                        ->searchable()->preload()->disabled($terkunci),

                    TextInput::make('counted_by')
                        ->label('Dihitung oleh')->maxLength(255)->disabled($terkunci),
                ]),

            Section::make('Hasil Hitung Fisik')
                ->description('Selisih dihitung otomatis: fisik − sistem. Hanya baris yang selisih yang mengoreksi stok, dan koreksinya tercatat di kartu mutasi.')
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
                                ->live(),

                            Select::make('rack_id')
                                ->label('Rak')
                                ->options(fn () => Rack::with('warehouse')->where('is_active', true)->get()
                                    ->mapWithKeys(fn (Rack $r) => [$r->id => $r->fullName()]))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    // Bekukan stok sistem untuk rak yang dipilih.
                                    $qty = MaterialStock::where('material_id', $get('material_id'))
                                        ->where('rack_id', $state)
                                        ->value('qty');
                                    $set('system_qty', (float) ($qty ?? 0));
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
