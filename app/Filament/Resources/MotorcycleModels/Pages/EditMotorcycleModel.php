<?php

namespace App\Filament\Resources\MotorcycleModels\Pages;

use App\Filament\Resources\MotorcycleModels\MotorcycleModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycleModel extends EditRecord
{
    protected static string $resource = MotorcycleModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
