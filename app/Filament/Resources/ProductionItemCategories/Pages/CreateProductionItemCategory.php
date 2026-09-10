<?php

namespace App\Filament\Resources\ProductionItemCategories\Pages;

use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionItemCategory extends CreateRecord
{
    protected static string $resource = ProductionItemCategoryResource::class;
}
