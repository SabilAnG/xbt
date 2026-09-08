<?php

namespace App\Filament\Resources\MotorcycleBrands\Pages;

use App\Filament\Resources\MotorcycleBrands\MotorcycleBrandResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMotorcycleBrand extends EditRecord
{
    protected static string $resource = MotorcycleBrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
