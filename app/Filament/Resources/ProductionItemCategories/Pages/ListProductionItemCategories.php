<?php

namespace App\Filament\Resources\ProductionItemCategories\Pages;

use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionItemCategories extends ListRecords
{
    protected static string $resource = ProductionItemCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
