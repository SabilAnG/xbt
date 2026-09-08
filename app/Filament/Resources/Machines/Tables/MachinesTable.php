<?php

namespace App\Filament\Resources\Machines\Tables;

use App\Models\Machine;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MachinesTable
{
    public static function configure(Table $table): Table
    {
        $guard = TableActions::notInUse(['formulaLines' => 'baris formula']);

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Mesin')->searchable()->sortable()->weight('medium')
                    ->description(fn (Machine $record) => $record->description),

                TextColumn::make('code')->label('Kode')->badge()->color('gray')->searchable(),

                TextColumn::make('purchase_price')->label('Harga')->money('IDR')->alignRight()
                    ->description(fn (Machine $record) => $record->economic_life_years.' tahun · '
                        .rtrim(rtrim((string) $record->hours_per_year, '0'), '.').' jam/thn')
                    ->toggleable(),

                TextColumn::make('power_kw')->label('Daya')->alignRight()
                    ->formatStateUsing(fn ($state) => rtrim(rtrim((string) $state, '0'), '.').' kW')
                    ->toggleable(),

                TextColumn::make('penyusutan')->label('Penyusutan')->alignRight()->money('IDR')
                    ->getStateUsing(fn (Machine $record) => $record->depreciationPerHour())
                    ->description('per jam')->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('listrik')->label('Listrik')->alignRight()->money('IDR')
                    ->getStateUsing(fn (Machine $record) => $record->electricityPerHour())
                    ->description('per jam')->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('biaya_jam')
                    ->label('Biaya / Jam')
                    ->alignRight()->weight('bold')->color('primary')->money('IDR')
                    ->getStateUsing(fn (Machine $record) => $record->hourlyCost())
                    ->description('susut + listrik + maintenance'),

                TextColumn::make('formula_lines_count')->counts('formulaLines')
                    ->label('Dipakai formula')->alignCenter()->badge(),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->placeholder('Semua'),
            ])
            ->headerActions([
                TableExport::action('mesin', [
                    'Kode' => fn (Machine $r) => $r->code,
                    'Mesin' => fn (Machine $r) => $r->name,
                    'Harga' => fn (Machine $r) => (float) $r->purchase_price,
                    'Umur (tahun)' => fn (Machine $r) => $r->economic_life_years,
                    'Jam per Tahun' => fn (Machine $r) => (float) $r->hours_per_year,
                    'Daya (kW)' => fn (Machine $r) => (float) $r->power_kw,
                    'Maintenance per Tahun' => fn (Machine $r) => (float) $r->maintenance_per_year,
                    'Penyusutan per Jam' => fn (Machine $r) => $r->depreciationPerHour(),
                    'Listrik per Jam' => fn (Machine $r) => $r->electricityPerHour(),
                    'Maintenance per Jam' => fn (Machine $r) => $r->maintenancePerHour(),
                    'Biaya per Jam' => fn (Machine $r) => $r->hourlyCost(),
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada mesin')
            ->emptyStateDescription('Daftarkan mesin las, gerinda, bending, roll, dan poles beserta harga dan dayanya.')
            ->emptyStateIcon('heroicon-o-cog-8-tooth');
    }
}
