<?php

namespace App\Filament\Resources\ProductionItems;

use App\Filament\Resources\ProductionItems\Pages\CreateProductionItem;
use App\Filament\Resources\ProductionItems\Pages\EditProductionItem;
use App\Filament\Resources\ProductionItems\Pages\ListProductionItems;
use App\Filament\Resources\ProductionItems\Schemas\ProductionItemForm;
use App\Filament\Resources\ProductionItems\Tables\ProductionItemsTable;
use App\Models\ProductionItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionItemResource extends Resource
{
    protected static ?string $model = ProductionItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Barang Produksi';

    protected static ?string $pluralModelLabel = 'Barang Produksi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function getNavigationBadge(): ?string
    {
        $menipis = static::getModel()::query()
            ->where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        return $menipis > 0 ? (string) $menipis : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionItems::route('/'),
            'create' => CreateProductionItem::route('/create'),
            'edit' => EditProductionItem::route('/{record}/edit'),
        ];
    }
}
