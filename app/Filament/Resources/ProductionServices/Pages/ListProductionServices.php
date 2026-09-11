<?php

namespace App\Filament\Resources\ProductionServices\Pages;

use App\Filament\Resources\ProductionServices\ProductionServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionServices extends ListRecords
{
    protected static string $resource = ProductionServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Jasa'),
        ];
    }
}
