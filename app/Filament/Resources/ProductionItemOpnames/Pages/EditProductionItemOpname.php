<?php

namespace App\Filament\Resources\ProductionItemOpnames\Pages;

use App\Filament\Resources\ProductionItemOpnames\ProductionItemOpnameResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionItemOpname extends EditRecord
{
    protected static string $resource = ProductionItemOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
