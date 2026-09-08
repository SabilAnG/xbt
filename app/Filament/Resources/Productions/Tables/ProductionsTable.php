<?php

namespace App\Filament\Resources\Productions\Tables;

use App\Models\Production;
use App\Models\Vendor;
use App\Services\ProductionPostingService;
use App\Services\ShoppingListService;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('produced_at', 'desc')
            ->columns([
                TextColumn::make('production_number')->label('No.')->searchable()->sortable()->weight('bold'),
                TextColumn::make('produced_at')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('formula.name')->label('Formula')->searchable()->wrap()
                    ->description(fn (Production $r) => $r->plan
                        ? 'dari rencana '.$r->plan->plan_number
                        : null),

                TextColumn::make('output_qty')->label('Hasil')->alignRight()
                    ->formatStateUsing(fn ($state, Production $record) => rtrim(rtrim((string) $state, '0'), '.').' '.($record->formula->output_unit ?? '')),

                TextColumn::make('total_cost')->label('Total Biaya')->money('IDR')->alignRight()->sortable()
                    ->description(fn (Production $r) => $r->isPosted()
                        ? 'bahan '.number_format((float) $r->material_cost, 0, ',', '.')
                            .' + kerja '.number_format((float) $r->service_cost, 0, ',', '.')
                            .' + mesin '.number_format((float) $r->machine_cost, 0, ',', '.')
                        : null),

                TextColumn::make('total_minutes')->label('Waktu Kerja')->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').' menit')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hpp_per_unit')
                    ->label('HPP / unit')
                    ->money('IDR')->alignRight()->weight('bold')->sortable()
                    ->color('primary')
                    ->placeholder('—'),

                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Production::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'posted' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Production::STATUSES),
                SelectFilter::make('formula_id')->label('Formula')
                    ->relationship('formula', 'name')->searchable()->preload(),
                Filter::make('periode')
                    ->schema([
                        DatePicker::make('dari')->label('Dari tanggal'),
                        DatePicker::make('sampai')->label('Sampai tanggal'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('produced_at', '>=', $v))
                        ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('produced_at', '<=', $v))),
            ])
            ->recordActions([
                Action::make('isiDariFormula')
                    ->label('Ambil dari formula')
                    ->icon('heroicon-o-arrow-path')->color('info')
                    ->requiresConfirmation()
                    ->modalDescription('Baris bahan dan biaya akan ditimpa sesuai formula dikali jumlah resep.')
                    ->visible(fn (Production $r) => ! $r->isPosted())
                    ->action(function (Production $record, ProductionPostingService $posting) {
                        try {
                            $posting->fillFromFormula($record);
                            Notification::make()->success()->title('Bahan & biaya diambil dari formula')->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('belanjaKekurangan')
                    ->label('Beli bahan yang kurang')
                    ->icon('heroicon-o-shopping-cart')->color('warning')
                    ->visible(function (Production $r) {
                        if ($r->isPosted() || ! $r->formula) {
                            return false;
                        }

                        return ! $r->formula->requirementFor($r->targetUnit())['cukup'];
                    })
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->options(fn () => Vendor::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->helperText('Boleh dikosongkan dan diisi nanti di notanya.'),
                    ])
                    ->modalHeading('Buat nota pembelian untuk bahan yang kurang?')
                    ->modalDescription(function (Production $r) {
                        $cek = $r->formula->requirementFor($r->targetUnit());

                        return sprintf(
                            '%d bahan kurang, perkiraan belanja Rp %s. Nota dibuat sebagai draft — '
                            .'stok baru bertambah setelah notanya dibukukan.',
                            count($cek['kurang']),
                            number_format($cek['biaya_belanja'], 0, ',', '.')
                        );
                    })
                    ->action(function (Production $record, array $data, ShoppingListService $belanja) {
                        try {
                            $cek = $record->formula->requirementFor($record->targetUnit());

                            $nota = $belanja->createPurchaseFromShortage(
                                $cek['kurang'],
                                $data['vendor_id'] ?? null,
                                'Kekurangan bahan untuk produksi '.$record->production_number
                            );
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title('Nota tidak dibuat')->body($e->getMessage())->persistent()->send();

                            return;
                        }

                        Notification::make()->success()
                            ->title('Nota pembelian '.$nota->invoice_number.' dibuat')
                            ->body('Periksa harga dan jumlahnya di menu Pembelian Bahan, lalu bukukan.')
                            ->send();
                    }),

                Action::make('post')
                    ->label('Bukukan')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Stok bahan berkurang dan HPP dihitung lalu dibekukan ke nota ini.')
                    ->visible(fn (Production $r) => ! $r->isPosted())
                    ->action(function (Production $record, ProductionPostingService $posting) {
                        try {
                            $posting->postProduction($record);
                            $record->refresh();
                            Notification::make()->success()
                                ->title('Produksi dibukukan')
                                ->body('HPP per unit: Rp '.number_format((float) $record->hpp_per_unit, 0, ',', '.'))
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()->title('Gagal membukukan')->body($e->getMessage())->persistent()->send();
                        }
                    }),

                Action::make('unpost')
                    ->label('Batalkan')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Stok bahan dikembalikan dan perhitungan HPP dihapus.')
                    ->visible(fn (Production $r) => $r->isPosted())
                    ->action(function (Production $record, ProductionPostingService $posting) {
                        $posting->unpostProduction($record);
                        Notification::make()->success()->title('Nota dikembalikan ke draft')->send();
                    }),

                EditAction::make()->label('Ubah'),
                TableActions::delete(TableActions::onlyDraft()),
            ])
            ->headerActions([
                TableExport::action('produksi', [
                    'No. Produksi' => fn (Production $r) => $r->production_number,
                    'Tanggal' => fn (Production $r) => $r->produced_at,
                    'Formula' => fn (Production $r) => $r->formula?->name,
                    'Dari Rencana' => fn (Production $r) => $r->plan?->plan_number,
                    'Gudang' => fn (Production $r) => $r->warehouse?->name,
                    'Batch' => fn (Production $r) => (float) $r->batch_qty,
                    'Unit Dihasilkan' => fn (Production $r) => (float) $r->output_qty,
                    'Biaya Bahan' => fn (Production $r) => (float) $r->material_cost,
                    'Biaya Tenaga Kerja' => fn (Production $r) => (float) $r->service_cost,
                    'Total Menit Kerja' => fn (Production $r) => (float) $r->total_minutes,
                    'Biaya Mesin' => fn (Production $r) => (float) $r->machine_cost,
                    'Overhead' => fn (Production $r) => (float) $r->overhead_cost,
                    'Total Biaya' => fn (Production $r) => (float) $r->total_cost,
                    'HPP per Unit' => fn (Production $r) => (float) $r->hpp_per_unit,
                    'Status' => fn (Production $r) => Production::STATUSES[$r->status] ?? $r->status,
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(TableActions::onlyDraft())]),
            ])
            ->emptyStateHeading('Belum ada produksi')
            ->emptyStateDescription('Jalankan formula untuk menghitung HPP nyata dan mengurangi stok bahan.')
            ->emptyStateIcon('heroicon-o-wrench-screwdriver');
    }
}
