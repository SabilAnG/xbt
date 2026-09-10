<?php

namespace App\Filament\Resources\ProductionItems\Pages;

use App\Filament\Resources\ProductionItems\ProductionItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionItem extends CreateRecord
{
    protected static string $resource = ProductionItemResource::class;
}
