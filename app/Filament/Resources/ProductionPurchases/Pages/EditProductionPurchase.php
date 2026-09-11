<?php

namespace App\Filament\Resources\ProductionPurchases\Pages;

use App\Filament\Resources\ProductionPurchases\ProductionPurchaseResource;
use App\Support\TableActions;
use Filament\Resources\Pages\EditRecord;

class EditProductionPurchase extends EditRecord
{
    protected static string $resource = ProductionPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TableActions::delete(TableActions::onlyDraft()),
        ];
    }
}
