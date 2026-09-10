<?php

namespace App\Filament\Resources\ProductionItems\Pages;

use App\Filament\Resources\ProductionItems\ProductionItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionItems extends ListRecords
{
    protected static string $resource = ProductionItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Barang'),
        ];
    }
}
