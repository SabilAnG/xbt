<?php

namespace App\Filament\Resources\ProductionItemOpnames\Pages;

use App\Filament\Resources\ProductionItemOpnames\ProductionItemOpnameResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionItemOpnames extends ListRecords
{
    protected static string $resource = ProductionItemOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Buat Opname'),
        ];
    }
}
