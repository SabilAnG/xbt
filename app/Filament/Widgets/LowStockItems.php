<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Barang yang stoknya sudah menyentuh atau di bawah batas minimum.
 */
class LowStockItems extends TableWidget
{
    protected static ?string $heading = 'Stok Perlu Diisi';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Item::query()
                    ->where('is_active', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock')
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Semua stok aman')
            ->emptyStateDescription('Tidak ada barang yang menyentuh batas minimum.')
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                TextColumn::make('name')
                    ->label('Barang')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Item $r) => $r->sku),

                TextColumn::make('stock')
                    ->label('Sisa')
                    ->alignRight()
                    ->badge()
                    ->formatStateUsing(fn ($state, Item $r) => rtrim(rtrim((string) $state, '0'), '.').' '.$r->unit)
                    ->color(fn (Item $r) => (float) $r->stock <= 0 ? 'danger' : 'warning'),

                TextColumn::make('min_stock')
                    ->label('Min')
                    ->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.'))
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('buka')
                    ->label('Buka')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Item $record) => ItemResource::getUrl('edit', ['record' => $record]))
                    ->color('gray'),
            ]);
    }
}
