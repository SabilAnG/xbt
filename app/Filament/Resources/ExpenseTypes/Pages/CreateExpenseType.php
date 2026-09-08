<?php

namespace App\Filament\Resources\ExpenseTypes\Pages;

use App\Filament\Resources\ExpenseTypes\ExpenseTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpenseType extends CreateRecord
{
    protected static string $resource = ExpenseTypeResource::class;
}
