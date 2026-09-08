<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Gudang')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Gudang Komponen, Gudang Finishing.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('code')) && filled($state)) {
                                // "Gudang Komponen" -> "GUDANG-KOMPONEN"
                                $set('code', strtoupper(Str::slug($state)));
                            }
                        }),

                    TextInput::make('code')
                        ->label('Kode')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->helperText('Terisi otomatis dari nama.'),

                    TextInput::make('address')->label('Alamat')->maxLength(255)->columnSpanFull(),
                    TextInput::make('description')->label('Keterangan')->maxLength(255)->columnSpanFull(),

                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }
}
