<?php

namespace App\Filament\Resources\ItemTypes;

use App\Filament\Resources\ItemTypes\Pages\ListItemTypes;
use App\Filament\Resources\ItemTypes\Schemas\ItemTypeForm;
use App\Filament\Resources\ItemTypes\Tables\ItemTypesTable;
use App\Models\ItemType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemTypeResource extends Resource
{
    protected static ?string $model = ItemType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Produk';

    protected static ?string $pluralModelLabel = 'Produk';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
    }

    public static function form(Schema $schema): Schema
    {
        return ItemTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemTypesTable::configure($table);
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
            'index' => ListItemTypes::route('/'),
        ];
    }
}
