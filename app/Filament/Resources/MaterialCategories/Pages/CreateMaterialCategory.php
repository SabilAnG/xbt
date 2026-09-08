<?php

namespace App\Filament\Resources\MaterialCategories\Pages;

use App\Filament\Resources\MaterialCategories\MaterialCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialCategory extends CreateRecord
{
    protected static string $resource = MaterialCategoryResource::class;
}
