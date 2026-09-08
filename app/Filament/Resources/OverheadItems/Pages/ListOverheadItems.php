<?php

namespace App\Filament\Resources\OverheadItems\Pages;

use App\Filament\Resources\OverheadItems\OverheadItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverheadItems extends ListRecords
{
    protected static string $resource = OverheadItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
