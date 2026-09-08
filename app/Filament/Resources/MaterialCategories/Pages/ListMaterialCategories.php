<?php

namespace App\Filament\Resources\MaterialCategories\Pages;

use App\Filament\Resources\MaterialCategories\MaterialCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterialCategories extends ListRecords
{
    protected static string $resource = MaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
