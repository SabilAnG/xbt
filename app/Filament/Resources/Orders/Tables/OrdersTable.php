<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->searchable()
                    ->description(fn (Order $r) => $r->customer_email ?: $r->customer_phone)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'shipped', 'in_transit' => 'info',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('shipments_count')
                    ->counts('shipments')
                    ->label('Shipments')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Order::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),

                DeleteAction::make()
                    ->label('Hapus')
                    ->modalDescription('Order beserta seluruh pengiriman dan riwayat trackingnya akan dihapus.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus terpilih'),
                ]),
            ])
            ->emptyStateHeading('Belum ada order')
            ->emptyStateDescription('Order yang dicatat di sini bisa dilacak pembeli lewat halaman /tracking.')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
