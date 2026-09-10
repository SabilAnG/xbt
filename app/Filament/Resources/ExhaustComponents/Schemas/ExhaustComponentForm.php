<?php

namespace App\Filament\Resources\ExhaustComponents\Schemas;

use App\Models\ExhaustComponent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExhaustComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description('Daftar bagian penyusun knalpot. Bahan dan ukurannya diisi nanti di formula, karena berbeda tiap model motor.')
                ->columns(2)
                ->schema([
                    Select::make('parent_id')
                        ->label('Bagian')
                        ->options(fn () => ExhaustComponent::query()
                            ->bagian()->orderBy('sort_order')->pluck('name', 'id'))
                        ->searchable()->live()
                        ->placeholder('— Ini bagian induk —')
                        ->helperText('Kosongkan bila yang dibuat justru bagian induknya, seperti Header atau Silincer.')
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Nama Bagian')->required()->maxLength(255)
                                ->helperText('Contoh: Header, Silincer, Leher.'),
                        ])
                        ->createOptionUsing(fn (array $data) => ExhaustComponent::create($data)->getKey()),

                    TextInput::make('name')
                        ->label('Nama Komponen')->required()->maxLength(255)
                        ->helperText('Contoh: P1, Tabung Silincer, Braket Atas.'),

                    TextInput::make('code')
                        ->label('Kode')->maxLength(64)
                        ->helperText('Opsional — dipakai bila bengkel menyebutnya dengan kode.'),

                    TextInput::make('sort_order')
                        ->label('Urutan')->numeric()->default(0)
                        ->helperText('Angka kecil tampil lebih dulu.'),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    Textarea::make('notes')
                        ->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
