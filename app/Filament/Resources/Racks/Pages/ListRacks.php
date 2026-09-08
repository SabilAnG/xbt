<?php

namespace App\Filament\Resources\Racks\Pages;

use App\Filament\Resources\Racks\RackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRacks extends ListRecords
{
    protected static string $resource = RackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
