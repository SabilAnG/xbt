<?php

namespace App\Filament\Resources\MotorcycleModels\Schemas;

use App\Models\MotorcycleBrand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MotorcycleModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('motorcycle_brand_id')
                        ->label('Brand')
                        ->relationship('brand', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nama Brand')->required(),
                            TextInput::make('slug')->required()
                                ->default(fn (callable $get) => Str::slug((string) $get('name'))),
                        ]),

                    TextInput::make('name')
                        ->label('Type Motor')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Vario 160, Sportster.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('slug')) && filled($state)) {
                                // Slug memuat brand agar "Beat" Honda dan Yamaha tidak bentrok.
                                $brand = MotorcycleBrand::find($get('motorcycle_brand_id'));
                                $set('slug', Str::slug(trim(($brand?->name ?? '').' '.$state)));
                            }
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Terisi otomatis dari brand + type.'),

                    TextInput::make('year_from')
                        ->label('Tahun mulai')
                        ->numeric()
                        ->minValue(1950)
                        ->maxValue((int) date('Y') + 1),

                    TextInput::make('year_to')
                        ->label('Tahun sampai')
                        ->numeric()
                        ->minValue(1950)
                        ->maxValue((int) date('Y') + 1)
                        ->helperText('Kosongkan bila masih diproduksi.'),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
        ]);
    }
}
