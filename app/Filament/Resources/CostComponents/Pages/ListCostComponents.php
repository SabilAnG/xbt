<?php

namespace App\Filament\Resources\CostComponents\Pages;

use App\Filament\Resources\CostComponents\CostComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCostComponents extends ListRecords
{
    protected static string $resource = CostComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
