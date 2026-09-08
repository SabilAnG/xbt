<?php

namespace App\Filament\Resources\OverheadItems\Tables;

use App\Models\OverheadItem;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OverheadItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Pos Biaya')->searchable()->sortable()->weight('medium')
                    ->description(fn (OverheadItem $r) => $r->notes),

                TextColumn::make('monthly_cost')->label('Per Bulan')->money('IDR')->alignRight()->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR')),

                TextColumn::make('per_unit')->label('Beban / Unit')->alignRight()->money('IDR')
                    ->getStateUsing(fn (OverheadItem $r) => $r->is_active
                        ? (float) $r->monthly_cost / OverheadItem::targetPerMonth()
                        : 0)
                    ->description(fn () => 'target '
                        .rtrim(rtrim((string) OverheadItem::targetPerMonth(), '0'), '.').' unit/bln'),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->headerActions([
                TableExport::action('overhead-bulanan', [
                    'Pos Biaya' => fn (OverheadItem $r) => $r->name,
                    'Biaya per Bulan' => fn (OverheadItem $r) => (float) $r->monthly_cost,
                    'Beban per Unit' => fn (OverheadItem $r) => (float) $r->monthly_cost / OverheadItem::targetPerMonth(),
                    'Keterangan' => fn (OverheadItem $r) => $r->notes,
                    'Aktif' => fn (OverheadItem $r) => $r->is_active ? 'Ya' : 'Tidak',
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                // Pos overhead tidak dirujuk transaksi mana pun — nilainya
                // dijumlah saat dihitung — jadi aman dihapus kapan saja.
                TableActions::delete(fn () => null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(fn () => null)]),
            ])
            ->emptyStateHeading('Belum ada biaya tetap')
            ->emptyStateDescription('Daftarkan sewa, listrik penerangan, internet, dan gaji non-produksi.')
            ->emptyStateIcon('heroicon-o-building-office-2');
    }
}
