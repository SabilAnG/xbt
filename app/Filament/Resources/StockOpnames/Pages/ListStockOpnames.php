<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Urutan kerja sebenarnya: cetak kertasnya dulu, hitung di gudang,
            // baru sesinya dibuat. Karena itu tombol ini harus ada walau daftar
            // masih kosong.
            Action::make('lembarKosong')
                ->label('Cetak Lembar Hitung')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => StockOpnameResource::getUrl('lembar-kosong')),

            CreateAction::make(),
        ];
    }
}
