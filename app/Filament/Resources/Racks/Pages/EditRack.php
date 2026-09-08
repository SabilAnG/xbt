<?php

namespace App\Filament\Resources\Racks\Pages;

use App\Filament\Resources\Racks\RackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRack extends EditRecord
{
    protected static string $resource = RackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
