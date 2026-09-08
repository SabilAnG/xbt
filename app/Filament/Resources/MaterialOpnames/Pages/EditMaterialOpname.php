<?php

namespace App\Filament\Resources\MaterialOpnames\Pages;

use App\Filament\Resources\MaterialOpnames\MaterialOpnameResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialOpname extends EditRecord
{
    protected static string $resource = MaterialOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lembarHitung')
                ->label('Lembar Hitung')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => MaterialOpnameResource::getUrl('lembar-hitung', ['record' => $this->record])),

            DeleteAction::make(),
        ];
    }
}
