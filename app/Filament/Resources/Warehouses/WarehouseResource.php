<?php

namespace App\Filament\Resources\Warehouses;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Warehouses\Pages\IsiGudang;
use App\Filament\Resources\Warehouses\Pages\ListWarehouses;
use App\Filament\Resources\Warehouses\Schemas\WarehouseForm;
use App\Filament\Resources\Warehouses\Tables\WarehousesTable;
use App\Models\Warehouse;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WarehouseResource extends BaseResource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Gudang';

    protected static ?string $pluralModelLabel = 'Gudang';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return WarehouseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarehousesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarehouses::route('/'),
            // Dibuka dengan mengklik kartunya di daftar gudang.
            'isi' => IsiGudang::route('/{record}/isi'),
        ];
    }
}
