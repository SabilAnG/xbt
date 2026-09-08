<?php

namespace App\Filament\Resources\CostComponents\Tables;

use App\Models\CostComponent;
use App\Support\TableActions;
use App\Support\TableExport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CostComponentsTable
{
    public static function configure(Table $table): Table
    {
        $guard = TableActions::notInUse(['formulaLines' => 'baris formula']);

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Biaya')->searchable()->sortable()->weight('medium')
                    ->description(fn (CostComponent $r) => $r->description),

                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state) => CostComponent::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'jasa' => 'info',
                        'tenaga_kerja' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('rate_type')->label('Tipe')->badge()
                    ->formatStateUsing(fn (?string $state) => $state === 'per_hour' ? 'Per jam' : 'Borongan')
                    ->color(fn (?string $state) => $state === 'per_hour' ? 'success' : 'gray'),

                TextColumn::make('rate')->label('Tarif')->money('IDR')->alignRight()->sortable()
                    ->description(fn (CostComponent $r) => 'per '.($r->isHourly() ? 'jam' : $r->unit)),

                TextColumn::make('default_minutes')->label('Menit Standar')->alignRight()
                    ->formatStateUsing(fn (?string $state, CostComponent $r) => $r->isHourly()
                        ? rtrim(rtrim((string) $state, '0'), '.').' menit'
                        : '-')
                    ->description(fn (CostComponent $r) => $r->isHourly()
                        ? 'Rp '.number_format($r->costForMinutes((float) $r->default_minutes), 0, ',', '.')
                        : null)
                    ->toggleable(),

                TextColumn::make('formula_lines_count')->counts('formulaLines')
                    ->label('Dipakai formula')->alignCenter()->badge(),

                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(CostComponent::TYPES),
                SelectFilter::make('rate_type')->label('Tipe Tarif')->options(CostComponent::RATE_TYPES),
            ])
            ->headerActions([
                TableExport::action('komponen-biaya', [
                    'Nama' => fn (CostComponent $r) => $r->name,
                    'Jenis' => fn (CostComponent $r) => CostComponent::TYPES[$r->type] ?? $r->type,
                    'Tipe Tarif' => fn (CostComponent $r) => $r->isHourly() ? 'Per jam' : 'Borongan',
                    'Tarif' => fn (CostComponent $r) => (float) $r->rate,
                    'Satuan' => fn (CostComponent $r) => $r->isHourly() ? 'jam' : $r->unit,
                    'Menit Standar' => fn (CostComponent $r) => $r->isHourly() ? (float) $r->default_minutes : '',
                    'Aktif' => fn (CostComponent $r) => $r->is_active ? 'Ya' : 'Tidak',
                ]),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete($guard),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk($guard)]),
            ])
            ->emptyStateHeading('Belum ada komponen biaya')
            ->emptyStateDescription('Contoh: Potong Pipa Rp 25.000 per jam, atau Poles Rp 35.000 borongan.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
