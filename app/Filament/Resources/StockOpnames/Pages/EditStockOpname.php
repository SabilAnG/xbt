<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStockOpname extends EditRecord
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lembarHitung')
                ->label('Lembar Hitung')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => StockOpnameResource::getUrl('lembar-hitung', ['record' => $this->record])),

            DeleteAction::make(),
        ];
    }
}
