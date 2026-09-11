<?php

namespace App\Filament\Resources\PriceTiers;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\PriceTiers\Pages\ListPriceTiers;
use App\Filament\Resources\PriceTiers\Schemas\PriceTierForm;
use App\Filament\Resources\PriceTiers\Tables\PriceTiersTable;
use App\Models\PriceTier;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PriceTierResource extends BaseResource
{
    protected static ?string $model = PriceTier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Tingkatan Harga';

    protected static ?string $pluralModelLabel = 'Tingkatan Harga';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Data';
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
        ];
    }
}
