<?php

namespace App\Filament\Resources\ItemCategories;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ItemCategories\Pages\ListItemCategories;
use App\Filament\Resources\ItemCategories\Schemas\ItemCategoryForm;
use App\Filament\Resources\ItemCategories\Tables\ItemCategoriesTable;
use App\Models\ItemCategory;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemCategoryResource extends BaseResource
{
    protected static ?string $model = ItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Kategori Produk';

    protected static ?string $pluralModelLabel = 'Kategori Produk';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return ItemCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemCategoriesTable::configure($table);
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
            'index' => ListItemCategories::route('/'),
        ];
    }
}
