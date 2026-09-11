<?php

namespace App\Filament\Resources\Warehouses\Schemas;

use App\Models\Warehouse;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Gudang')->required()->maxLength(255)
                        ->helperText('Contoh: Gudang Bahan Mentah.'),

                    TextInput::make('code')
                        ->label('Kode')->required()->maxLength(16)
                        ->unique(ignoreRecord: true)
                        ->helperText('Singkat saja: BM, BSJ, FG, BS.'),

                    Select::make('type')
                        ->label('Jenis Gudang')
                        ->options(Warehouse::TYPES)
                        ->default('bahan_mentah')->required()
                        ->helperText('Keterangan dan bawaan saja — sistem tidak menolak barang yang disimpan di gudang lain. Bengkel sering menitipkan sementara, dan memaksanya hanya membuat orang mengakali sistem.'),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    TextInput::make('sort_order')
                        ->label('Urutan')->numeric()->default(0)
                        ->helperText('Angka kecil tampil lebih dulu.'),

                    Textarea::make('description')
                        ->label('Keterangan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
