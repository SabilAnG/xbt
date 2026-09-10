<?php

namespace App\Filament\Resources\PriceTiers\Tables;

use App\Models\Item;
use App\Models\PriceTier;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PriceTiersTable
{
    public static function configure(Table $table): Table
    {
        // Satu modal acuan dipakai untuk seluruh baris supaya kolom harga bisa
        // dibandingkan langsung antar tingkatan. Diambil dari barang jual
        // termahal yang modalnya sudah terisi — angka nyata, bukan karangan.
        //
        // Dulu acuannya HPP formula. Modul produksi sedang dibangun ulang;
        // kembalikan ke HPP begitu formula ada lagi.
        $acuan = Item::query()
            ->where('is_active', true)->where('cost_price', '>', 0)
            ->orderByDesc('cost_price')->first();
        $hpp = (float) ($acuan?->cost_price ?? 0);

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Tingkatan')->searchable()->sortable()->weight('medium')
                    ->description(fn (PriceTier $r) => $r->notes),

                TextColumn::make('margin_percent')->label('Margin')->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').'%')
                    ->description('dari harga jual'),

                TextColumn::make('fee_percent')->label('Potongan')->alignRight()
                    ->formatStateUsing(fn ($state) => (float) $state > 0
                        ? rtrim(rtrim((string) $state, '0'), '.').'%'
                        : '—'),

                TextColumn::make('harga')->label('Harga Jual')->alignRight()->money('IDR')
                    ->weight('bold')->color('primary')
                    ->getStateUsing(fn (PriceTier $r) => $r->price($hpp))
                    ->description($acuan ? 'modal '.$acuan->name : 'belum ada barang bermodal'),

                TextColumn::make('laba')->label('Laba Bersih')->alignRight()->money('IDR')
                    ->color('success')
                    ->getStateUsing(fn (PriceTier $r) => $r->breakdown($hpp)['laba'])
                    ->description(fn (PriceTier $r) => sprintf('%.1f%% nyata', $r->breakdown($hpp)['margin_nyata'])),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->headerActions([
                TableExport::action('tingkatan-harga', [
                    'Tingkatan' => fn (PriceTier $r) => $r->name,
                    'Margin %' => fn (PriceTier $r) => (float) $r->margin_percent,
                    'Potongan %' => fn (PriceTier $r) => (float) $r->fee_percent,
                    'Harga Jual' => fn (PriceTier $r) => $r->price($hpp),
                    'Laba Bersih' => fn (PriceTier $r) => $r->breakdown($hpp)['laba'],
                    'Aktif' => fn (PriceTier $r) => $r->is_active ? 'Ya' : 'Tidak',
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(fn () => null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(fn () => null)]),
            ])
            ->emptyStateHeading('Belum ada tingkatan harga')
            ->emptyStateDescription('Contoh: Reseller 20%, Toko 30%, Retail 40%, Marketplace 40% + potongan 12%.')
            ->emptyStateIcon('heroicon-o-tag');
    }
}
