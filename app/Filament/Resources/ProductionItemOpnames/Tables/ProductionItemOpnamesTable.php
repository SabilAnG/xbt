<?php

namespace App\Filament\Resources\ProductionItemOpnames\Tables;

use App\Models\ProductionItemOpname;
use App\Services\ProductionStockService;
use App\Support\TableActions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class ProductionItemOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opname_date', 'desc')
            ->columns([
                TextColumn::make('opname_number')->label('No. Opname')
                    ->searchable()->sortable()->weight('bold'),

                TextColumn::make('opname_date')->label('Tanggal')->date('d M Y')->sortable(),

                TextColumn::make('counted_by')->label('Petugas')->placeholder('—')->toggleable(),

                TextColumn::make('items_count')->counts('items')->label('Barang')->alignCenter(),

                TextColumn::make('selisih')
                    ->label('Baris selisih')->alignCenter()->badge()
                    ->getStateUsing(fn (ProductionItemOpname $r) => $r->discrepancyCount())
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => ProductionItemOpname::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProductionItemOpname::STATUSES),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Bukukan stok opname ini?')
                    ->modalDescription('Setiap selisih dicatat sebagai koreksi di kartu stok, jadi perubahannya bisa ditelusuri. Baris yang cocok tidak meninggalkan apa-apa.')
                    ->visible(fn (ProductionItemOpname $record) => ! $record->isPosted())
                    ->action(function (ProductionItemOpname $record, ProductionStockService $stok) {
                        try {
                            $stok->postOpname($record);
                            Notification::make()->success()->title('Stok opname dibukukan')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()
                                ->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan pembukuan?')
                    ->modalDescription('Koreksi dari opname ini dihapus dari kartu stok, lalu stok dihitung ulang dari baris yang tersisa.')
                    ->visible(fn (ProductionItemOpname $record) => $record->isPosted())
                    ->action(function (ProductionItemOpname $record, ProductionStockService $stok) {
                        $stok->unpostOpname($record);
                        Notification::make()->success()->title('Dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),

                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(TableActions::onlyDraft())]),
            ])
            ->emptyStateHeading('Belum ada stok opname')
            ->emptyStateDescription('Opname juga cara mengisi stok pertama kali — barang yang belum pernah berstok tercatat 0, jadi hitungan fisiknya langsung jadi stok awal.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}
