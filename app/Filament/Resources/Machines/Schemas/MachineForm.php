<?php

namespace App\Filament\Resources\Machines\Schemas;

use App\Models\Machine;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MachineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mesin')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Mesin')
                        ->required()->maxLength(255)
                        ->helperText('Contoh: Mesin Las TIG, Mesin Bending Pipa.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('code')) && filled($state)) {
                                $set('code', strtoupper(Str::slug($state)));
                            }
                        }),

                    TextInput::make('code')->label('Kode')->required()
                        ->unique(ignoreRecord: true)->maxLength(64),

                    TextInput::make('description')->label('Keterangan')
                        ->maxLength(255)->columnSpanFull(),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                ]),

            Section::make('Perhitungan Biaya per Jam')
                ->description('Biaya per jam tidak diketik — diturunkan dari harga mesin, umur pakainya, daya listrik, dan maintenance. Listrik dihitung di sini agar tidak terhitung dua kali bersama overhead.')
                ->columns(3)
                ->schema([
                    TextInput::make('purchase_price')
                        ->label('Harga Mesin')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')
                        ->live(onBlur: true),

                    TextInput::make('economic_life_years')
                        ->label('Umur Ekonomis (tahun)')
                        ->numeric()->default(5)->minValue(1)
                        ->live(onBlur: true),

                    TextInput::make('hours_per_year')
                        ->label('Jam Pakai / Tahun')
                        ->numeric()->default(1000)->minValue(1)
                        ->live(onBlur: true)
                        ->helperText('Perkiraan jam mesin benar-benar menyala.'),

                    TextInput::make('power_kw')
                        ->label('Daya (kW)')
                        ->numeric()->default(0)->minValue(0)->step(0.1)
                        ->live(onBlur: true)
                        ->helperText('2.5 untuk las 2500 watt.'),

                    TextInput::make('maintenance_per_year')
                        ->label('Maintenance / Tahun')
                        ->numeric()->default(0)->minValue(0)->prefix('Rp')
                        ->live(onBlur: true),

                    Placeholder::make('tarif_listrik')
                        ->label('Tarif listrik dipakai')
                        ->content(fn () => 'Rp '.number_format(Machine::tarifListrik(), 0, ',', '.').'/kWh'),

                    // Hasilnya ditampilkan langsung supaya angka aneh ketahuan
                    // sebelum dipakai formula.
                    Placeholder::make('hasil')
                        ->label('Biaya per jam')
                        ->columnSpanFull()
                        ->content(function ($get) {
                            $m = new Machine([
                                'purchase_price' => (float) ($get('purchase_price') ?: 0),
                                'economic_life_years' => (int) ($get('economic_life_years') ?: 1),
                                'hours_per_year' => (float) ($get('hours_per_year') ?: 1),
                                'power_kw' => (float) ($get('power_kw') ?: 0),
                                'maintenance_per_year' => (float) ($get('maintenance_per_year') ?: 0),
                            ]);
                            $b = $m->hourlyBreakdown();
                            $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');

                            return sprintf(
                                'penyusutan %s + listrik %s + maintenance %s  =  %s per jam',
                                $rp($b['penyusutan']), $rp($b['listrik']), $rp($b['maintenance']), $rp($b['total'])
                            );
                        }),
                ]),
        ]);
    }
}
