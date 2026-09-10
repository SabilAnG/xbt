<?php

namespace App\Filament\Resources\ProductionItemCategories\Pages;

use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionItemCategory extends EditRecord
{
    protected static string $resource = ProductionItemCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
