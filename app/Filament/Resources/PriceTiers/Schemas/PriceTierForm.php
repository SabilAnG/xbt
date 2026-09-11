<?php

namespace App\Filament\Resources\PriceTiers\Schemas;

use App\Models\Item;
use App\Models\PriceTier;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PriceTierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tingkatan Harga')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Tingkatan')
                        ->required()->maxLength(255)
                        ->helperText('Contoh: Reseller, Toko, Retail, Marketplace.')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),

                    TextInput::make('notes')->label('Keterangan')->maxLength(255)->columnSpanFull(),

                    Toggle::make('is_active')->label('Aktif')->default(true),

                    TextInput::make('sort_order')->label('Urutan')->numeric()->default(0),
                ]),

            Section::make('Margin & Potongan')
                ->columnSpanFull()
                ->description('Margin dihitung dari harga jual, bukan dari modal. 40% berarti empat puluh persen dari uang yang masuk benar-benar jadi laba.')
                ->columns(2)
                ->schema([
                    TextInput::make('margin_percent')
                        ->label('Margin')
                        ->numeric()->required()->default(30)->minValue(0)->maxValue(95)->suffix('%')
                        ->live(onBlur: true),

                    TextInput::make('fee_percent')
                        ->label('Potongan Marketplace')
                        ->numeric()->default(0)->minValue(0)->maxValue(95)->suffix('%')
                        ->live(onBlur: true)
                        ->helperText('Isi 0 untuk penjualan langsung. Shopee/Tokopedia biasanya 8–12%.'),

                    // Contoh memakai modal barang jual termahal supaya angkanya
                    // nyata, bukan sekadar rumus di atas kertas. Dulu acuannya
                    // HPP formula; kembalikan ke sana setelah modul produksi
                    // selesai dibangun ulang.
                    Placeholder::make('contoh')
                        ->label('Contoh dengan barang bermodal tertinggi')
                        ->columnSpanFull()
                        ->content(function (callable $get) {
                            $barang = Item::query()
                                ->where('is_active', true)->where('cost_price', '>', 0)
                                ->orderByDesc('cost_price')->first();

                            if (! $barang) {
                                return 'Belum ada barang bermodal untuk dijadikan contoh.';
                            }

                            $tier = new PriceTier([
                                'margin_percent' => (float) ($get('margin_percent') ?: 0),
                                'fee_percent' => (float) ($get('fee_percent') ?: 0),
                            ]);

                            $modal = (float) $barang->cost_price;
                            $b = $tier->breakdown($modal);
                            $rp = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');

                            return sprintf(
                                '%s — modal %s → harga jual %s, potongan %s, laba bersih %s (%.1f%% dari harga).',
                                $barang->name, $rp($modal), $rp($b['harga']), $rp($b['potongan']),
                                $rp($b['laba']), $b['margin_nyata']
                            );
                        }),
                ]),
        ]);
    }
}
