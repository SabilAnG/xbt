<?php

namespace App\Filament\Resources\MotorcycleBrands\Pages;

use App\Filament\Resources\MotorcycleBrands\MotorcycleBrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMotorcycleBrands extends ListRecords
{
    protected static string $resource = MotorcycleBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
