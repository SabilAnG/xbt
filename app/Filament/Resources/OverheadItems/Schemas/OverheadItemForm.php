<?php

namespace App\Filament\Resources\OverheadItems\Schemas;

use App\Models\OverheadItem;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OverheadItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pos Biaya Tetap')
                ->description('Biaya yang tetap keluar tiap bulan walau tidak ada produksi. Listrik mesin tidak masuk sini — sudah dihitung per jam di master Mesin.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Pos')
                        ->required()->maxLength(255)
                        ->helperText('Contoh: Sewa Bengkel, Gaji Admin, Internet.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),

                    TextInput::make('monthly_cost')
                        ->label('Biaya per Bulan')
                        ->numeric()->required()->default(0)->minValue(0)->prefix('Rp')
                        ->live(onBlur: true),

                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),

                    TextInput::make('notes')->label('Keterangan')->maxLength(255)->columnSpanFull(),

                    Toggle::make('is_active')->label('Aktif')->default(true)
                        ->helperText('Pos tidak aktif tidak ikut membebani HPP.'),

                    Placeholder::make('beban')
                        ->label('Beban ke tiap knalpot')
                        ->columnSpanFull()
                        ->content(function () {
                            $total = OverheadItem::monthlyTotal();
                            $target = OverheadItem::targetPerMonth();
                            $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');

                            return sprintf(
                                'Total overhead aktif %s ÷ target %s unit/bulan = %s per unit. '
                                .'Target diatur di Pengaturan Produksi.',
                                $rp($total),
                                rtrim(rtrim((string) $target, '0'), '.'),
                                $rp($total / max($target, 1))
                            );
                        }),
                ]),
        ]);
    }
}
