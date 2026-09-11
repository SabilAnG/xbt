<?php

namespace App\Filament\Resources\ExpenseCategories;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use App\Filament\Resources\ExpenseCategories\Tables\ExpenseCategoriesTable;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseCategoryResource extends BaseResource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Kategori Pengeluaran';

    protected static ?string $pluralModelLabel = 'Kategori Pengeluaran';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseCategoriesTable::configure($table);
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
            'index' => ListExpenseCategories::route('/'),
        ];
    }
}
