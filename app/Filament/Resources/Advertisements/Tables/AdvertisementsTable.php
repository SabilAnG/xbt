<?php

namespace App\Filament\Resources\Advertisements\Tables;

use App\Models\Advertisement;
use App\Support\TableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AdvertisementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_path')->label('Banner')->height(40),

                TextColumn::make('title')
                    ->label('Nama Iklan')->searchable()->sortable()->weight('medium')
                    ->description(fn (Advertisement $r) => $r->advertiser_name),

                TextColumn::make('position')
                    ->label('Posisi')->badge()
                    ->formatStateUsing(fn (Advertisement $r) => $r->displayPosition()),

                TextColumn::make('masa')
                    ->label('Masa Tayang')
                    ->getStateUsing(fn (Advertisement $r) => $r->masaTayang())
                    ->color(fn (Advertisement $r) => $r->ends_at?->isPast() ? 'danger' : null),

                TextColumn::make('clicks')
                    ->label('Klik')->alignCenter()->sortable()->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                // Bukan sekadar tombol aktif: masa tayang ikut menentukan, dan
                // itu yang benar-benar dilihat pengunjung hari ini.
                TextColumn::make('tayang')
                    ->label('Sekarang')->badge()
                    ->getStateUsing(fn (Advertisement $r) => $r->sedangTayang() ? 'Tayang' : 'Tidak')
                    ->color(fn (Advertisement $r) => $r->sedangTayang() ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('position')->label('Posisi')->options(Advertisement::POSITIONS),
                TernaryFilter::make('is_active')->label('Aktif')->placeholder('Semua'),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                TableActions::delete(fn () => null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([TableActions::deleteBulk(fn () => null)]),
            ])
            ->emptyStateHeading('Belum ada iklan')
            ->emptyStateDescription('Pemasang menghubungi lewat tombol Pasang Iklan di footer situs.')
            ->emptyStateIcon('heroicon-o-megaphone');
    }
}
