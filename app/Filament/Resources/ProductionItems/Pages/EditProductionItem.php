<?php

namespace App\Filament\Resources\ProductionItems\Pages;

use App\Filament\Resources\ProductionItems\ProductionItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionItem extends EditRecord
{
    protected static string $resource = ProductionItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
