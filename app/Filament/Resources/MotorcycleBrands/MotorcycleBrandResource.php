<?php

namespace App\Filament\Resources\MotorcycleBrands;

use App\Filament\Resources\MotorcycleBrands\Pages\CreateMotorcycleBrand;
use App\Filament\Resources\MotorcycleBrands\Pages\EditMotorcycleBrand;
use App\Filament\Resources\MotorcycleBrands\Pages\ListMotorcycleBrands;
use App\Filament\Resources\MotorcycleBrands\Schemas\MotorcycleBrandForm;
use App\Filament\Resources\MotorcycleBrands\Tables\MotorcycleBrandsTable;
use App\Models\MotorcycleBrand;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MotorcycleBrandResource extends Resource
{
    protected static ?string $model = MotorcycleBrand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Brand Motor';

    protected static ?string $pluralModelLabel = 'Brand Motor';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return MotorcycleBrandForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MotorcycleBrandsTable::configure($table);
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
            'index' => ListMotorcycleBrands::route('/'),
            'create' => CreateMotorcycleBrand::route('/create'),
            'edit' => EditMotorcycleBrand::route('/{record}/edit'),
        ];
    }
}
