<?php

namespace App\Filament\Resources\ExhaustComponents\Pages;

use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExhaustComponents extends ListRecords
{
    protected static string $resource = ExhaustComponentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Komponen'),
        ];
    }
}
