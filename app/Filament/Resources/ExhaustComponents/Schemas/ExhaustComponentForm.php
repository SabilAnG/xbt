<?php

namespace App\Filament\Resources\ExhaustComponents\Schemas;

use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Models\ExhaustComponent;
use App\Models\ProductionItem;
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

                    // Bagian induk tidak dibuat dari apa pun; ia hanya wadah.
                    Select::make('production_item_id')
                        ->label('Bahan Baku')
                        ->options(fn () => ProductionItem::query()
                            ->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (callable $get) => filled($get('parent_id')))
                        ->columnSpanFull()
                        ->helperText('Dibuat dari bahan apa. Berapa banyak dan ukuran potongannya diisi nanti di formula, karena berbeda tiap model motor.')
                        ->createOptionForm(fn () => ProductionItemForm::ringkas())
                        ->createOptionUsing(fn (array $data) => ProductionItem::create($data)->getKey()),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    Textarea::make('notes')
                        ->label('Catatan')->rows(2)->columnSpanFull(),
                ]),
        ]);
    }
}
