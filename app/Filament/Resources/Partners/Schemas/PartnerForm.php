<?php

namespace App\Filament\Resources\Partners\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Hanya dibaca. Data partner datang dari pendaftarannya sendiri, dan yang boleh
 * diubah admin — hak akses dan masa pakai — dikerjakan lewat tombol di tabel,
 * bukan dengan menyunting baris.
 */
class PartnerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pendaftaran')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Nama Toko')->disabled(),
                    TextInput::make('slug')->label('Alamat Toko')->disabled(),
                    TextInput::make('owner_name')->label('Pemilik')->disabled(),
                    TextInput::make('owner_email')->label('Email')->disabled(),
                    TextInput::make('owner_phone')->label('WhatsApp')->disabled(),
                    Textarea::make('notes')->label('Catatan')->rows(3)->columnSpanFull()->disabled(),
                ]),
        ]);
    }
}
