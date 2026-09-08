<?php

namespace App\Filament\Resources\PriceTiers;

use App\Filament\Resources\PriceTiers\Pages\CreatePriceTier;
use App\Filament\Resources\PriceTiers\Pages\EditPriceTier;
use App\Filament\Resources\PriceTiers\Pages\ListPriceTiers;
use App\Filament\Resources\PriceTiers\Schemas\PriceTierForm;
use App\Filament\Resources\PriceTiers\Tables\PriceTiersTable;
use App\Models\PriceTier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceTierResource extends Resource
{
    protected static ?string $model = PriceTier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'Tingkatan Harga';

    protected static ?string $pluralModelLabel = 'Tingkatan Harga';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return PriceTierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PriceTiersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPriceTiers::route('/'),
            'create' => CreatePriceTier::route('/create'),
            'edit' => EditPriceTier::route('/{record}/edit'),
        ];
    }
}
