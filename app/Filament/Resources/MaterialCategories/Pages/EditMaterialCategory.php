<?php

namespace App\Filament\Resources\MaterialCategories\Pages;

use App\Filament\Resources\MaterialCategories\MaterialCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMaterialCategory extends EditRecord
{
    protected static string $resource = MaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
