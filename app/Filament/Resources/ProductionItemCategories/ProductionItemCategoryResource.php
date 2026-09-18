<?php

namespace App\Filament\Resources\ProductionItemCategories;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ProductionItemCategories\Pages\ListProductionItemCategories;
use App\Filament\Resources\ProductionItemCategories\Schemas\ProductionItemCategoryForm;
use App\Filament\Resources\ProductionItemCategories\Tables\ProductionItemCategoriesTable;
use App\Models\ProductionItemCategory;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionItemCategoryResource extends BaseResource
{
    protected static ?string $model = ProductionItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Jenis Barang';

    protected static ?string $pluralModelLabel = 'Jenis Barang';

    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionItemCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionItemCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionItemCategories::route('/'),
        ];
    }
}
