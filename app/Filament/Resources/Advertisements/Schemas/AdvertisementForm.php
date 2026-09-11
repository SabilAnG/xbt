<?php

namespace App\Filament\Resources\Advertisements\Schemas;

use App\Models\Advertisement;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdvertisementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Banner')
                ->description('Foto dikirim pemasang; posisinya Anda yang tentukan.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Nama Iklan')->required()->maxLength(255)
                        ->helperText('Untuk Anda sendiri, tidak tampil ke pengunjung.'),

                    Select::make('position')
                        ->label('Posisi di Halaman')
                        ->options(Advertisement::POSITIONS)
                        ->default('footer')->required(),

                    FileUpload::make('image_path')
                        ->label('Foto Banner')
                        ->image()
                        ->directory('iklan')
                        ->maxSize(2048)
                        ->required()
                        ->columnSpanFull()
                        ->helperText('Maksimal 2 MB. Banner melintang paling enak dilihat pada rasio 4:1.'),

                    TextInput::make('target_url')
                        ->label('Diklik menuju')
                        ->url()->required()->maxLength(255)
                        ->placeholder('https://instagram.com/tokonya')
                        ->columnSpanFull()
                        ->helperText('Situs, Instagram, WhatsApp — apa pun yang pemasang mau.'),
                ]),

            Section::make('Masa Tayang')
                ->description('Dikosongkan berarti tayang terus sampai dimatikan.')
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    DatePicker::make('starts_at')->label('Mulai'),

                    DatePicker::make('ends_at')
                        ->label('Sampai')
                        ->helperText('Lewat tanggal ini banner turun sendiri.'),

                    TextInput::make('sort_order')
                        ->label('Urutan')->numeric()->default(0)
                        ->helperText('Angka kecil tampil lebih dulu di posisi yang sama.'),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),

            Section::make('Pemasang')
                ->description('Untuk ditagih dan dihubungi. Tidak pernah tampil ke pengunjung.')
                ->columnSpanFull()
                ->columns(2)
                ->collapsed()
                ->schema([
                    TextInput::make('advertiser_name')->label('Nama')->maxLength(255),

                    TextInput::make('advertiser_contact')->label('Kontak')->maxLength(255)
                        ->placeholder('WhatsApp atau email'),

                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
