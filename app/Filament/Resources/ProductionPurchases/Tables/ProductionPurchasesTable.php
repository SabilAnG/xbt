<?php

namespace App\Filament\Resources\ProductionPurchases\Tables;

use App\Models\ProductionPurchase;
use App\Models\Warehouse;
use App\Services\ProductionStockService;
use App\Support\TableActions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.warehouse', 'warehouse', 'wallet']))
            ->defaultSort('purchased_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('No. Nota')->searchable()->sortable()->weight('medium')
                    ->description(fn (ProductionPurchase $r) => $r->supplier_name),

                TextColumn::make('purchased_at')
                    ->label('Tanggal')->date('d M Y')->sortable(),

                // Gudangnya milik tiap baris, jadi satu nota bisa menyebut
                // lebih dari satu — yang ditampilkan gudang yang benar-benar
                // dituju barisnya, bukan gudang bawaan notanya.
                TextColumn::make('gudang')
                    ->label('Masuk Gudang')->wrap()
                    ->getStateUsing(fn (ProductionPurchase $r) => $r->items
                        ->map(fn ($baris) => $baris->warehouse?->name ?? $r->warehouse?->name)
                        ->filter()->unique()->implode(', '))
                    ->placeholder('—'),

                TextColumn::make('items_count')
                    ->label('Bahan')->counts('items')->alignCenter(),

                TextColumn::make('total')
                    ->label('Total')->money('IDR')->alignRight()->sortable()
                    ->description(fn (ProductionPurchase $r) => $r->wallet?->name ?? 'belum dibayar'),

                TextColumn::make('status')
                    ->label('Status')->badge()
                    ->formatStateUsing(fn (ProductionPurchase $r) => $r->displayStatus())
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ProductionPurchase::STATUSES),

                // Disaring lewat barisnya, bukan lewat gudang bawaan notanya:
                // nota yang bawaannya gudang A tapi barisnya gudang B memang
                // mengisi gudang B, dan itu yang dicari orang.
                SelectFilter::make('gudang')
                    ->label('Gudang')
                    ->options(fn () => Warehouse::query()
                        ->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('items', fn (Builder $q) => $q->where('warehouse_id', $data['value']))
                        : $query),

                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('purchased_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('purchased_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Stok bahan di gudang tujuan bertambah dan saldo dompet berkurang. Nota masih bisa dibatalkan setelahnya.')
                    ->visible(fn (ProductionPurchase $record) => ! $record->isPosted())
                    ->action(function (ProductionPurchase $record, ProductionStockService $stok) {
                        try {
                            $stok->postPurchase($record);
                            Notification::make()->success()->title('Pembelian dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Mutasi stok dan kas dari nota ini dihapus, lalu saldo dihitung ulang dari baris yang tersisa.')
                    ->visible(fn (ProductionPurchase $record) => $record->isPosted())
                    ->action(function (ProductionPurchase $record, ProductionStockService $stok) {
                        $stok->unpostPurchase($record);
                        Notification::make()->success()->title('Nota dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(TableActions::onlyDraft())]),
            ])
            ->emptyStateHeading('Belum ada pembelian bahan')
            ->emptyStateDescription('Catat nota dari toko besi di sini; stok gudang bertambah setelah dibukukan.')
            ->emptyStateIcon('heroicon-o-truck');
    }
}
