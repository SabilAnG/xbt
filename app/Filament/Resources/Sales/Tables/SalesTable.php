<?php

namespace App\Filament\Resources\Sales\Tables;

use App\Models\Sale;
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

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sold_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('No. Nota')->searchable()->sortable()->weight('bold'),
                TextColumn::make('sold_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('customer_name')->label('Pembeli')->searchable()->placeholder('—'),
                TextColumn::make('items_count')->counts('items')->label('Baris')->alignCenter(),
                TextColumn::make('total')->label('Total')->money('IDR')->sortable()->alignRight(),

                TextColumn::make('laba')
                    ->label('Laba')
                    ->alignRight()
                    ->getStateUsing(fn (Sale $r) => $r->isPosted() ? $r->grossProfit() : null)
                    ->money('IDR')
                    ->placeholder('—')
                    ->color(fn (?string $state) => $state !== null && (float) $state < 0 ? 'danger' : 'success')
                    ->description('total − modal'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Sale::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Sale::STATUSES),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
                        ->when($data['sampai'] ?? null, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Stok barang berkurang dan saldo dompet bertambah. Modal dibekukan di angka harga beli saat ini.')
                    ->visible(fn (Sale $record) => ! $record->isPosted())
                    ->action(function (Sale $record, PostingService $posting) {
                        try {
                            $posting->postSale($record);
                            Notification::make()->success()->title('Penjualan dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Sale $record) => $record->isPosted())
                    ->action(function (Sale $record, PostingService $posting) {
                        $posting->unpostSale($record);
                        Notification::make()->success()->title('Nota dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->headerActions([
                TableExport::action('penjualan', [
                    'No. Nota' => fn (Sale $r) => $r->invoice_number,
                    'Tanggal' => fn (Sale $r) => $r->sold_at,
                    'Pembeli' => fn (Sale $r) => $r->customer_name,
                    'No. HP' => fn (Sale $r) => $r->customer_phone,
                    'Dompet' => fn (Sale $r) => $r->wallet?->name,
                    'Subtotal' => fn (Sale $r) => (float) $r->subtotal,
                    'Diskon' => fn (Sale $r) => (float) $r->discount,
                    'Total' => fn (Sale $r) => (float) $r->total,
                    'Modal' => fn (Sale $r) => (float) $r->total_cost,
                    'Laba' => fn (Sale $r) => $r->isPosted() ? $r->grossProfit() : null,
                    'Status' => fn (Sale $r) => Sale::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(TableActions::onlyDraft()),
                ]),
            ])
            ->emptyStateHeading('Belum ada penjualan')
            ->emptyStateDescription('Catat nota penjualan untuk mengurangi stok dan menambah kas.')
            ->emptyStateIcon('heroicon-o-arrow-up-tray');
    }
}
