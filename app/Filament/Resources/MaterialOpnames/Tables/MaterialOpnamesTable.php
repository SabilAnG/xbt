<?php

namespace App\Filament\Resources\MaterialOpnames\Tables;

use App\Filament\Resources\MaterialOpnames\MaterialOpnameResource;
use App\Models\MaterialOpname;
use App\Services\ProductionPostingService;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialOpnamesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opname_date', 'desc')
            ->columns([
                TextColumn::make('opname_number')->label('No.')->searchable()->sortable()->weight('bold'),
                TextColumn::make('opname_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('warehouse.name')->label('Gudang')->placeholder('—')->searchable(),
                TextColumn::make('counted_by')->label('Petugas')->placeholder('—')->toggleable(),
                TextColumn::make('items_count')->counts('items')->label('Bahan')->alignCenter(),

                TextColumn::make('selisih')
                    ->label('Baris selisih')
                    ->alignCenter()->badge()
                    ->getStateUsing(fn (MaterialOpname $record) => $record->discrepancyCount())
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => MaterialOpname::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(MaterialOpname::STATUSES),
            ])
            ->headerActions([
                TableExport::action('stok-opname-bahan', [
                    'No. Opname' => fn (MaterialOpname $r) => $r->opname_number,
                    'Tanggal' => fn (MaterialOpname $r) => $r->opname_date,
                    'Gudang' => fn (MaterialOpname $r) => $r->warehouse?->name,
                    'Petugas' => fn (MaterialOpname $r) => $r->counted_by,
                    'Jumlah Bahan' => fn (MaterialOpname $r) => $r->items()->count(),
                    'Baris Selisih' => fn (MaterialOpname $r) => $r->discrepancyCount(),
                    'Status' => fn (MaterialOpname $r) => MaterialOpname::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->recordActions([
                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Setiap selisih dibukukan sebagai koreksi stok yang tercatat di kartu mutasi bahan.')
                    ->visible(fn (MaterialOpname $record) => ! $record->isPosted())
                    ->action(function (MaterialOpname $record, ProductionPostingService $posting) {
                        try {
                            $posting->postMaterialOpname($record);
                            Notification::make()->success()->title('Stok opname bahan dibukukan')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Koreksi stok dari opname ini dihapus, lalu stok dihitung ulang.')
                    ->visible(fn (MaterialOpname $record) => $record->isPosted())
                    ->action(function (MaterialOpname $record, ProductionPostingService $posting) {
                        $posting->unpostMaterialOpname($record);
                        Notification::make()->success()->title('Dikembalikan ke draft')->send();
                    }),

                Action::make('lembarHitung')
                    ->label('Lembar Hitung')
                    ->icon('heroicon-o-printer')->color('gray')
                    ->tooltip('Kertas kosong untuk mencatat hitungan di rak')
                    ->url(fn (MaterialOpname $record) => MaterialOpnameResource::getUrl('lembar-hitung', ['record' => $record])),

                EditAction::make()->label('Ubah'),
                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(TableActions::onlyDraft())]),
            ])
            ->emptyStateHeading('Belum ada stok opname bahan')
            ->emptyStateDescription('Hitung fisik pipa dan plat di rak, lalu samakan dengan catatan sistem.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }
}
