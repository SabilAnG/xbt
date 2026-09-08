<?php

namespace App\Filament\Resources\OverheadItems\Pages;

use App\Filament\Resources\OverheadItems\OverheadItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverheadItem extends EditRecord
{
    protected static string $resource = OverheadItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
