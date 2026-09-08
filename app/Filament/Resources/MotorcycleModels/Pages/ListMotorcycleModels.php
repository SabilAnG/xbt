<?php

namespace App\Filament\Resources\MotorcycleModels\Pages;

use App\Filament\Resources\MotorcycleModels\MotorcycleModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycleModels extends ListRecords
{
    protected static string $resource = MotorcycleModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
