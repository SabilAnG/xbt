<?php

namespace App\Filament\Resources\ProductionPlans\Tables;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Models\ProductionPlan;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('planned_for', 'desc')
            ->columns([
                TextColumn::make('plan_number')->label('No.')->searchable()->sortable()->weight('bold')
                    ->description(fn (ProductionPlan $r) => $r->title),

                TextColumn::make('planned_for')->label('Periode')->date('d M Y')->sortable(),

                TextColumn::make('lines_count')->counts('lines')->label('Formula')->alignCenter()->badge(),

                TextColumn::make('target')->label('Target')->alignRight()
                    ->getStateUsing(fn (ProductionPlan $r) => rtrim(rtrim(
                        number_format($r->targetUnits(), 2, ',', '.'), '0'), ',').' unit'),

                TextColumn::make('modal')->label('Modal Produksi')->alignRight()->money('IDR')
                    ->getStateUsing(fn (ProductionPlan $r) => $r->loadMissing(
                        ['lines.formula.materials.material', 'lines.formula.costs.component', 'lines.formula.machines.machine']
                    )->productionCost())
                    ->description('HPP x target'),

                TextColumn::make('belanja')->label('Perlu Belanja')->alignRight()->money('IDR')
                    ->weight('bold')
                    ->getStateUsing(fn (ProductionPlan $r) => $r->shoppingCost())
                    ->color(fn (ProductionPlan $r) => $r->shoppingCost() > 0 ? 'warning' : 'success')
                    ->description(fn (ProductionPlan $r) => $r->shoppingCost() > 0
                        ? count($r->shoppingList()).' bahan kurang'
                        : 'stok cukup'),

                TextColumn::make('materialPurchase.invoice_number')->label('Nota Beli')
                    ->badge()->color('info')->placeholder('belum dibuat')->toggleable(),

                TextColumn::make('productions_count')->counts('productions')
                    ->label('Nota Produksi')->alignCenter()->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state.' nota' : 'belum dibuat'),
            ])
            ->recordActions([
                Action::make('belanja')
                    ->label('Daftar Belanja')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('primary')
                    ->url(fn (ProductionPlan $record) => ProductionPlanResource::getUrl('belanja', ['record' => $record])),

                EditAction::make()->label('Ubah'),

                // Rencana bukan dokumen buku besar, tapi menghapusnya setelah
                // notanya dibuat akan memutus jejak asal-usul pembelian.
                TableActions::delete(function (ProductionPlan $r) {
                    if ($r->isShopped()) {
                        return 'Rencana ini sudah dibuatkan nota pembelian '
                            .($r->materialPurchase?->invoice_number ?? '').'. Hapus notanya dulu.';
                    }

                    return $r->hasProductions()
                        ? 'Rencana ini sudah dibuatkan nota produksi. Hapus notanya dulu.'
                        : null;
                }),
            ])
            ->headerActions([
                TableExport::action('rencana-produksi', [
                    'No. Rencana' => fn (ProductionPlan $r) => $r->plan_number,
                    'Periode' => fn (ProductionPlan $r) => $r->planned_for,
                    'Judul' => fn (ProductionPlan $r) => $r->title,
                    'Target Unit' => fn (ProductionPlan $r) => $r->targetUnits(),
                    'Modal Produksi' => fn (ProductionPlan $r) => $r->productionCost(),
                    'Perlu Belanja' => fn (ProductionPlan $r) => $r->shoppingCost(),
                    'Nota Pembelian' => fn (ProductionPlan $r) => $r->materialPurchase?->invoice_number,
                    'Jumlah Nota Produksi' => fn (ProductionPlan $r) => $r->productions()->count(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TableActions::deleteBulk(fn (ProductionPlan $r) => $r->isShopped() || $r->hasProductions()
                        ? 'Sudah dibuatkan nota pembelian atau nota produksi.'
                        : null),
                ]),
            ])
            ->emptyStateHeading('Belum ada rencana produksi')
            ->emptyStateDescription('Susun target bulan depan, lalu sistem menyiapkan daftar belanjanya.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
