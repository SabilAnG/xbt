<?php

namespace App\Filament\Resources\CostComponents\Pages;

use App\Filament\Resources\CostComponents\CostComponentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCostComponent extends EditRecord
{
    protected static string $resource = CostComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
