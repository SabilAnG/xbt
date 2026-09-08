<?php

namespace App\Filament\Resources\CostComponents\Schemas;

use App\Models\CostComponent;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CostComponentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Komponen Biaya')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Biaya')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Potong Pipa, Las Sambungan, Poles Akhir.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),

                    Select::make('type')
                        ->label('Jenis')
                        ->options(CostComponent::TYPES)
                        ->default('tenaga_kerja')
                        ->required(),

                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),

                    TextInput::make('description')->label('Keterangan')->maxLength(255)->columnSpanFull(),

                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),

            Section::make('Cara Menghitung')
                ->description('Pekerjaan yang lamanya berubah-ubah sebaiknya per jam — formula tinggal mengisi menitnya. Pekerjaan berharga tetap seperti poles borongan pakai per unit.')
                ->columns(3)
                ->schema([
                    Select::make('rate_type')
                        ->label('Tipe Tarif')
                        ->options(CostComponent::RATE_TYPES)
                        ->default('per_hour')
                        ->required()
                        ->live()
                        ->columnSpan(3),

                    TextInput::make('rate')
                        ->label(fn (callable $get) => $get('rate_type') === 'per_hour' ? 'Upah per Jam' : 'Tarif per Unit')
                        ->numeric()->required()->default(0)->minValue(0)->prefix('Rp'),

                    TextInput::make('unit')
                        ->label('Satuan Tarif')
                        ->default('jam')
                        ->required()
                        ->helperText(fn (callable $get) => $get('rate_type') === 'per_hour'
                            ? 'Isi "jam" saja.'
                            : 'Contoh: titik, set, unit.'),

                    TextInput::make('default_minutes')
                        ->label('Menit Standar')
                        ->numeric()->default(0)->minValue(0)->suffix('menit')
                        ->visible(fn (callable $get) => $get('rate_type') === 'per_hour')
                        ->helperText('Terisi otomatis saat proses ini dipakai di formula. Masih bisa diubah per formula.'),

                    Placeholder::make('contoh')
                        ->label('Contoh perhitungan')
                        ->columnSpanFull()
                        ->content(function (callable $get) {
                            $c = new CostComponent([
                                'rate_type' => $get('rate_type') ?: 'per_hour',
                                'rate' => (float) ($get('rate') ?: 0),
                                'unit' => $get('unit') ?: 'unit',
                            ]);
                            $menit = (float) ($get('default_minutes') ?: 0);

                            if (! $c->isHourly()) {
                                return sprintf('1 x %s = Rp %s', $c->displayRate(),
                                    number_format($c->costForMinutes(0, 1), 0, ',', '.'));
                            }

                            return sprintf('%s menit @ %s = Rp %s',
                                rtrim(rtrim(number_format($menit, 2, ',', '.'), '0'), ','),
                                $c->displayRate(),
                                number_format($c->costForMinutes($menit, 1), 0, ',', '.'));
                        }),
                ]),
        ]);
    }
}
