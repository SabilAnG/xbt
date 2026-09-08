<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Models\ProductionPlan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionPlan extends EditRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('belanja')
                ->label('Daftar Belanja')
                ->icon('heroicon-o-shopping-cart')
                ->url(fn (ProductionPlan $record) => ProductionPlanResource::getUrl('belanja', ['record' => $record])),

            DeleteAction::make()
                ->label('Hapus'),
        ];
    }
}
