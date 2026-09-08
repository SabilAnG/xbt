<?php

namespace App\Filament\Resources\OverheadItems;

use App\Filament\Resources\OverheadItems\Pages\CreateOverheadItem;
use App\Filament\Resources\OverheadItems\Pages\EditOverheadItem;
use App\Filament\Resources\OverheadItems\Pages\ListOverheadItems;
use App\Filament\Resources\OverheadItems\Schemas\OverheadItemForm;
use App\Filament\Resources\OverheadItems\Tables\OverheadItemsTable;
use App\Models\OverheadItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OverheadItemResource extends Resource
{
    protected static ?string $model = OverheadItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 70;

    protected static ?string $modelLabel = 'Overhead Bulanan';

    protected static ?string $pluralModelLabel = 'Overhead Bulanan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return OverheadItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OverheadItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOverheadItems::route('/'),
            'create' => CreateOverheadItem::route('/create'),
            'edit' => EditOverheadItem::route('/{record}/edit'),
        ];
    }
}
