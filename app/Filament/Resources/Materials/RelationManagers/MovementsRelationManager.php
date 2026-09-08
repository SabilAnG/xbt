<?php

namespace App\Filament\Resources\Materials\RelationManagers;

use App\Models\MaterialMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kartu stok bahan baku — riwayat masuk/keluar satu bahan.
 *
 * Baris di sini dibuat oleh ProductionPostingService, tidak pernah diketik
 * langsung, jadi tabel ini sengaja read-only.
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
                TextColumn::make('moved_at')->label('Tanggal')->dateTime('d M Y')->sortable(),

                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => MaterialMovement::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'purchase' => 'success',
                        'production' => 'info',
                        'opname' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('referensi')
                    ->label('Dokumen')
                    ->getStateUsing(function (MaterialMovement $record) {
                        $src = $record->source;

                        return $src->invoice_number
                            ?? $src->production_number
                            ?? '—';
                    })
                    ->description(fn (MaterialMovement $record) => $record->notes),

                TextColumn::make('rack.code')
                    ->label('Rak')
                    ->badge()->color('gray')
                    ->placeholder('—'),

                TextColumn::make('qty_in')
                    ->label('Masuk')->alignRight()->color('success')
                    ->formatStateUsing(fn ($state) => (float) $state > 0
                        ? '+'.rtrim(rtrim((string) $state, '0'), '.')
                        : '—'),

                TextColumn::make('qty_out')
                    ->label('Keluar')->alignRight()->color('danger')
                    ->formatStateUsing(fn ($state) => (float) $state > 0
                        ? '−'.rtrim(rtrim((string) $state, '0'), '.')
                        : '—'),

                TextColumn::make('balance_after')
                    ->label('Sisa')->alignRight()->weight('bold')
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.')),

                TextColumn::make('unit_cost')
                    ->label('Harga Satuan')->money('IDR')->alignRight()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(MaterialMovement::TYPES),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('moved_at', '>=', $v))
                        ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('moved_at', '<=', $v))),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Belum ada pergerakan')
            ->emptyStateDescription('Stok bergerak setelah pembelian bahan atau nota produksi dibukukan.');
    }
}
