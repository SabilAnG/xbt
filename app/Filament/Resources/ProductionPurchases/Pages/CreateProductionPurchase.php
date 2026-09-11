<?php

namespace App\Filament\Resources\ProductionPurchases\Pages;

use App\Filament\Resources\ProductionPurchases\ProductionPurchaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionPurchase extends CreateRecord
{
    protected static string $resource = ProductionPurchaseResource::class;
}
