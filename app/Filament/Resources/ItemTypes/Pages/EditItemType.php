<?php

namespace App\Filament\Resources\ItemTypes\Pages;

use App\Filament\Resources\ItemTypes\ItemTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemType extends EditRecord
{
    protected static string $resource = ItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
