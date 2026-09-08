<?php

namespace App\Filament\Resources\MaterialPurchases\Pages;

use App\Filament\Resources\MaterialPurchases\MaterialPurchaseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialPurchase extends CreateRecord
{
    protected static string $resource = MaterialPurchaseResource::class;
}
