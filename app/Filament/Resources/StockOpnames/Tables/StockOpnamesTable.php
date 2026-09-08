<?php

namespace App\Filament\Resources\StockOpnames\Tables;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\StockOpname;
use App\Services\PostingService;
use App\Support\TableActions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opname_date', 'desc')
            ->columns([
                TextColumn::make('opname_number')->label('No. Opname')->searchable()->sortable()->weight('bold'),
                TextColumn::make('opname_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('counted_by')->label('Petugas')->placeholder('—'),
                TextColumn::make('items_count')->counts('items')->label('Barang')->alignCenter(),

                TextColumn::make('selisih')
                    ->label('Baris selisih')
                    ->alignCenter()
                    ->badge()
                    ->getStateUsing(fn (StockOpname $r) => $r->discrepancyCount())
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => StockOpname::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(StockOpname::STATUSES),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Setiap selisih dibukukan sebagai koreksi stok yang tercatat di kartu stok.')
                    ->visible(fn (StockOpname $record) => ! $record->isPosted())
                    ->action(function (StockOpname $record, PostingService $posting) {
                        try {
                            $posting->postOpname($record);
                            Notification::make()->success()->title('Stok opname dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('lembarHitung')
                    ->label('Lembar Hitung')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->tooltip('Kertas kosong untuk mencatat hitungan di gudang')
                    ->url(fn (StockOpname $record) => StockOpnameResource::getUrl('lembar-hitung', ['record' => $record])),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(TableActions::onlyDraft()),
                ]),
            ])
            ->emptyStateHeading('Belum ada stok opname')
            ->emptyStateDescription('Buat sesi hitung fisik untuk menyamakan stok sistem dengan gudang.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}
