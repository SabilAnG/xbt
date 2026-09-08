<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Models\Purchase;
use App\Services\PostingService;
use App\Support\TableActions;
use App\Support\TableExport;
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

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchased_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('No. Nota')->searchable()->sortable()->weight('bold'),
                TextColumn::make('purchased_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('supplier_name')->label('Supplier')->searchable()->placeholder('—'),
                TextColumn::make('items_count')->counts('items')->label('Baris')->alignCenter(),
                TextColumn::make('total')->label('Total')->money('IDR')->sortable()->alignRight(),
                TextColumn::make('wallet.name')->label('Dompet')->placeholder('—')->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Purchase::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Purchase::STATUSES),
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
                    ->modalDescription('Stok barang bertambah dan saldo dompet berkurang. Nota masih bisa dibatalkan setelahnya.')
                    ->visible(fn (Purchase $record) => ! $record->isPosted())
                    ->action(function (Purchase $record, PostingService $posting) {
                        try {
                            $posting->postPurchase($record);
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
                    ->modalDescription('Mutasi stok dan kas dari nota ini dihapus, lalu saldo dihitung ulang.')
                    ->visible(fn (Purchase $record) => $record->isPosted())
                    ->action(function (Purchase $record, PostingService $posting) {
                        $posting->unpostPurchase($record);
                        Notification::make()->success()->title('Nota dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->headerActions([
                TableExport::action('pembelian', [
                    'No. Nota' => fn (Purchase $r) => $r->invoice_number,
                    'Tanggal' => fn (Purchase $r) => $r->purchased_at,
                    'Supplier' => fn (Purchase $r) => $r->supplier_name,
                    'Dompet' => fn (Purchase $r) => $r->wallet?->name,
                    'Subtotal' => fn (Purchase $r) => (float) $r->subtotal,
                    'Diskon' => fn (Purchase $r) => (float) $r->discount,
                    'Ongkir' => fn (Purchase $r) => (float) $r->shipping_cost,
                    'Total' => fn (Purchase $r) => (float) $r->total,
                    'Status' => fn (Purchase $r) => Purchase::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(TableActions::onlyDraft()),
                ]),
            ])
            ->emptyStateHeading('Belum ada pembelian')
            ->emptyStateDescription('Catat nota pembelian untuk menambah stok gudang.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray');
    }
}
