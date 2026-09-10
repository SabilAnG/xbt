<?php

namespace App\Filament\Resources\CostComponents;

use App\Filament\Resources\CostComponents\Pages\CreateCostComponent;
use App\Filament\Resources\CostComponents\Pages\EditCostComponent;
use App\Filament\Resources\CostComponents\Pages\ListCostComponents;
use App\Filament\Resources\CostComponents\Schemas\CostComponentForm;
use App\Filament\Resources\CostComponents\Tables\CostComponentsTable;
use App\Models\CostComponent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CostComponentResource extends Resource
{
    protected static ?string $model = CostComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?int $navigationSort = 150;

    protected static ?string $modelLabel = 'Komponen Biaya';

    protected static ?string $pluralModelLabel = 'Komponen Biaya';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return CostComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CostComponentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostComponents::route('/'),
            'create' => CreateCostComponent::route('/create'),
            'edit' => EditCostComponent::route('/{record}/edit'),
        ];
    }
}
