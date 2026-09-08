<?php

namespace App\Filament\Resources\ProductionPlans;

use App\Filament\Resources\ProductionPlans\Pages\CreateProductionPlan;
use App\Filament\Resources\ProductionPlans\Pages\DaftarBelanja;
use App\Filament\Resources\ProductionPlans\Pages\EditProductionPlan;
use App\Filament\Resources\ProductionPlans\Pages\ListProductionPlans;
use App\Filament\Resources\ProductionPlans\Schemas\ProductionPlanForm;
use App\Filament\Resources\ProductionPlans\Tables\ProductionPlansTable;
use App\Models\ProductionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionPlanResource extends Resource
{
    protected static ?string $model = ProductionPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Rencana Produksi';

    protected static ?string $pluralModelLabel = 'Rencana Produksi';

    protected static ?string $recordTitleAttribute = 'plan_number';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionPlans::route('/'),
            'create' => CreateProductionPlan::route('/create'),
            'edit' => EditProductionPlan::route('/{record}/edit'),
            'belanja' => DaftarBelanja::route('/{record}/belanja'),
        ];
    }
}
