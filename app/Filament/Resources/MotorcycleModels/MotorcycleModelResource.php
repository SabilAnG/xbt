<?php

namespace App\Filament\Resources\MotorcycleModels;

use App\Filament\Resources\MotorcycleModels\Pages\CreateMotorcycleModel;
use App\Filament\Resources\MotorcycleModels\Pages\EditMotorcycleModel;
use App\Filament\Resources\MotorcycleModels\Pages\ListMotorcycleModels;
use App\Filament\Resources\MotorcycleModels\Schemas\MotorcycleModelForm;
use App\Filament\Resources\MotorcycleModels\Tables\MotorcycleModelsTable;
use App\Models\MotorcycleModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MotorcycleModelResource extends Resource
{
    protected static ?string $model = MotorcycleModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Type Motor';

    protected static ?string $pluralModelLabel = 'Type Motor';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return MotorcycleModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MotorcycleModelsTable::configure($table);
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
            'index' => ListMotorcycleModels::route('/'),
            'create' => CreateMotorcycleModel::route('/create'),
            'edit' => EditMotorcycleModel::route('/{record}/edit'),
        ];
    }
}
