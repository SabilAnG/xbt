<?php

namespace App\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Bentuk form yang sama untuk semua master data sederhana.
 *
 * Slug terisi otomatis dari nama selama masih kosong; begitu satu baris sudah
 * dipakai transaksi, mengganti namanya tidak akan diam-diam mengubah slug.
 */
class MasterDataForm
{
    public static function build(
        Schema $schema,
        string $nameLabel,
        ?string $hint = null,
        bool $withDescription = true,
        bool $withSortOrder = true,
        array $extra = [],
    ): Schema {
        $fields = [
            TextInput::make('name')
                ->label($nameLabel)
                ->required()
                ->maxLength(255)
                ->helperText($hint)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                    if (blank($get('slug')) && filled($state)) {
                        $set('slug', Str::slug($state));
                    }
                }),

            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->helperText('Terisi otomatis dari nama. Ubah hanya bila perlu.'),
        ];

        $fields = array_merge($fields, $extra);

        if ($withDescription) {
            $fields[] = Textarea::make('description')
                ->label('Keterangan')
                ->rows(2)
                ->columnSpanFull();
        }

        if ($withSortOrder) {
            $fields[] = TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->default(0)
                ->helperText('Angka kecil tampil lebih dulu.');
        }

        $fields[] = Toggle::make('is_active')->label('Aktif')->default(true);

        return $schema->components([
            // Selebar wadahnya: form ini dibuka sebagai modal, dan skema modal
            // Filament dua kolom — tanpa ini kartunya cuma mengisi separuh kiri.
            Section::make()->columnSpanFull()->columns(2)->schema($fields),
        ]);
    }
}
