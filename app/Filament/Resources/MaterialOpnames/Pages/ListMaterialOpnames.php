<?php

namespace App\Filament\Resources\MaterialOpnames\Pages;

use App\Filament\Resources\MaterialOpnames\MaterialOpnameResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialOpnames extends ListRecords
{
    protected static string $resource = MaterialOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Urutan kerja sebenarnya: cetak kertasnya dulu, hitung di rak,
            // baru sesinya dibuat. Karena itu tombol ini harus ada walau daftar
            // masih kosong.
            Action::make('lembarKosong')
                ->label('Cetak Lembar Hitung')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => MaterialOpnameResource::getUrl('lembar-kosong')),

            CreateAction::make(),
        ];
    }
}
