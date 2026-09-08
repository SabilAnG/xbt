<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\StockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kartu stok — riwayat masuk/keluar satu barang.
 *
 * Baris di sini dibuat oleh PostingService, tidak pernah diketik langsung,
 * jadi tabel ini sengaja read-only.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Kartu Stok';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('moved_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => StockMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'purchase' => 'success',
                        'sale' => 'info',
                        'opname' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('referensi')
                    ->label('Dokumen')
                    ->getStateUsing(function (StockMovement $r) {
                        $src = $r->source;

                        return $src->invoice_number
                            ?? $src->opname_number
                            ?? $src->reference_number
                            ?? '—';
                    })
                    ->description(fn (StockMovement $r) => $r->notes),

                TextColumn::make('qty_in')
                    ->label('Masuk')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? '+'.rtrim(rtrim((string) $state, '0'), '.') : '—')
                    ->color('success'),

                TextColumn::make('qty_out')
                    ->label('Keluar')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => (float) $state > 0 ? '−'.rtrim(rtrim((string) $state, '0'), '.') : '—')
                    ->color('danger'),

                TextColumn::make('balance_after')
                    ->label('Sisa')
                    ->alignRight()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.')),

                TextColumn::make('unit_cost')
                    ->label('Harga Satuan')
                    ->money('IDR')
                    ->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(StockMovement::TYPES),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('moved_at', '<=', $d))),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada pergerakan')
            ->emptyStateDescription('Stok bergerak setelah nota pembelian, penjualan, atau stok opname dibukukan.');
    }
}
