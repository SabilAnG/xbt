<?php

namespace App\Filament\Resources\ProductionPurchases\Pages;

use App\Filament\Resources\ProductionPurchases\ProductionPurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionPurchases extends ListRecords
{
    protected static string $resource = ProductionPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Catat Pembelian'),
        ];
    }
}
