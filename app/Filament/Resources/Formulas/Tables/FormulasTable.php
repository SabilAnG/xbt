<?php

namespace App\Filament\Resources\Formulas\Tables;

use App\Models\Formula;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FormulasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Formula')->searchable()->sortable()->weight('medium')->wrap()
                    ->description(fn (Formula $r) => $r->code),

                TextColumn::make('motorcycleModel.name')
                    ->label('Type Motor')->searchable()->sortable()
                    ->getStateUsing(fn (Formula $r) => $r->motorcycleModel?->fullName())
                    ->placeholder('lintas motor'),

                TextColumn::make('lines_count')
                    ->label('Komponen')->counts('lines')->alignCenter(),

                TextColumn::make('kesiapan')
                    ->label('Kesiapan')->badge()
                    ->getStateUsing(function (Formula $r) {
                        $belum = $r->lineTanpaBahan()->count();

                        return $belum > 0 ? $belum.' belum ada bahan' : 'Lengkap';
                    })
                    ->color(fn (Formula $r) => $r->siap() ? 'success' : 'warning'),

                // Totalnya yang ditampilkan, rinciannya menyusul di bawah:
                // yang hanya melihat angka bahan akan mengira knalpot berchrome
                // semurah yang tidak.
                TextColumn::make('modal')
                    ->label('Modal / unit')->money('IDR')->alignRight()
                    ->getStateUsing(fn (Formula $r) => $r->totalCostPerUnit())
                    ->description(fn (Formula $r) => 'bahan '.self::rp($r->materialCostPerUnit())
                        .' + jasa '.self::rp($r->serviceCostPerUnit())),

                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('motorcycle_model_id')
                    ->label('Type Motor')
                    ->relationship('motorcycleModel', 'name')
                    ->searchable()->preload(),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),

                // Resep motor sebelah biasanya mirip; menyalin lalu mengubah
                // beberapa panjang jauh lebih cepat daripada mengisi dari nol.
                ReplicateAction::make()
                    ->label('Salin')
                    ->icon('heroicon-o-document-duplicate')
                    ->excludeAttributes(['code'])
                    ->beforeReplicaSaved(function (Formula $replica, Formula $record) {
                        $replica->code = $record->code.'-SALINAN';
                        $replica->name = $record->name.' (salinan)';
                    })
                    ->after(function (Formula $replica, Formula $record) {
                        foreach ($record->lines as $baris) {
                            $replica->lines()->create($baris->only([
                                'exhaust_component_id', 'production_item_id',
                                'input_mode', 'size_unit',
                                'piece_length_mm', 'piece_width_mm', 'piece_count',
                                'notes', 'sort_order',
                            ]));
                        }

                        // Ikut tersalin: resep motor sebelah biasanya dichrome
                        // dan dipoles sama saja, dan menyalin setengahnya
                        // membuat modal salinannya terlihat lebih murah.
                        foreach ($record->services as $jasa) {
                            $replica->services()->create($jasa->only([
                                'production_service_id', 'qty', 'notes', 'sort_order',
                            ]));
                        }
                    }),

                TableActions::delete(fn () => null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(fn () => null)]),
            ])
            ->emptyStateHeading('Belum ada formula')
            ->emptyStateDescription('Formula baru langsung terisi seluruh komponen — tinggal tentukan bahan dan ukurannya.')
            ->emptyStateIcon('heroicon-o-beaker');
    }

    private static function rp(float $angka): string
    {
        return 'Rp'.number_format($angka, 0, ',', '.');
    }
}
