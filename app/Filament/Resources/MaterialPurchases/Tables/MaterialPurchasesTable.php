<?php

namespace App\Filament\Resources\MaterialPurchases\Tables;

use App\Models\MaterialPurchase;
use App\Services\ProductionPostingService;
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

class MaterialPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('purchased_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('No. Nota')->searchable()->sortable()->weight('bold'),
                TextColumn::make('purchased_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable()->placeholder('—'),
                TextColumn::make('items_count')->counts('items')->label('Baris')->alignCenter(),
                TextColumn::make('total')->label('Total')->money('IDR')->sortable()->alignRight(),
                TextColumn::make('wallet.name')->label('Dompet')->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => MaterialPurchase::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(MaterialPurchase::STATUSES),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('purchased_at', '>=', $v))
                        ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('purchased_at', '<=', $v))),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Stok bahan bertambah di rak tujuan, saldo dompet berkurang, dan harga beli bahan diperbarui.')
                    ->visible(fn (MaterialPurchase $r) => ! $r->isPosted())
                    ->action(function (MaterialPurchase $record, ProductionPostingService $posting) {
                        try {
                            $posting->postPurchase($record);
                            Notification::make()->success()->title('Pembelian bahan dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Mutasi stok bahan dan kas dari nota ini dihapus, lalu dihitung ulang.')
                    ->visible(fn (MaterialPurchase $r) => $r->isPosted())
                    ->action(function (MaterialPurchase $record, ProductionPostingService $posting) {
                        $posting->unpostPurchase($record);
                        Notification::make()->success()->title('Nota dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),
                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->headerActions([
                TableExport::action('pembelian-bahan', [
                    'No. Nota' => fn (MaterialPurchase $r) => $r->invoice_number,
                    'Tanggal' => fn (MaterialPurchase $r) => $r->purchased_at,
                    'Vendor' => fn (MaterialPurchase $r) => $r->vendor?->name,
                    'Dompet' => fn (MaterialPurchase $r) => $r->wallet?->name,
                    'Subtotal' => fn (MaterialPurchase $r) => (float) $r->subtotal,
                    'Diskon' => fn (MaterialPurchase $r) => (float) $r->discount,
                    'Ongkir' => fn (MaterialPurchase $r) => (float) $r->shipping_cost,
                    'Total' => fn (MaterialPurchase $r) => (float) $r->total,
                    'Status' => fn (MaterialPurchase $r) => MaterialPurchase::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(TableActions::onlyDraft())]),
            ])
            ->emptyStateHeading('Belum ada pembelian bahan')
            ->emptyStateDescription('Catat pembelian pipa, plat, dan bahan lain di sini.')
            ->emptyStateIcon('heroicon-o-inbox-arrow-down');
    }
}
