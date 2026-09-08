<?php

namespace App\Filament\Resources\Racks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('warehouse_id')
                        ->label('Gudang')
                        ->relationship('warehouse', 'name')
                        ->searchable()->preload()->required()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nama Gudang')->required(),
                            TextInput::make('code')->label('Kode')->required(),
                        ]),

                    TextInput::make('code')
                        ->label('Kode Rak')
                        ->required()
                        ->maxLength(64)
                        ->helperText('Contoh: A1, B2. Cukup unik di dalam gudangnya, boleh sama antar gudang.'),

                    TextInput::make('name')
                        ->label('Nama / Posisi')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Rak Pipa Baris 1.'),

                    TextInput::make('description')->label('Keterangan')->maxLength(255),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }
}
