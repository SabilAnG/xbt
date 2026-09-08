<?php

namespace App\Filament\Resources\Vendors\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Vendor')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Toko atau supplier tempat membeli pipa, plat, dll.'),

                    TextInput::make('phone')->label('No. HP / Telepon')->tel()->maxLength(64),

                    TextInput::make('address')->label('Alamat')->maxLength(255)->columnSpanFull(),

                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }
}
