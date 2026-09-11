<?php

namespace App\Filament\Resources\Items;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Items\Pages\KartuStok;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Models\Item;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ItemResource extends BaseResource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Stok Barang';

    protected static ?string $pluralModelLabel = 'Stok Barang';

    public static function getNavigationGroup(): ?string
    {
        return 'Operasional';
    }

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Kartu stok punya halaman sendiri sekarang — relation manager tidak
        // bisa hidup di dalam modal, dan riwayat sepanjang itu memang tidak
        // pantas dijejalkan ke sana.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItems::route('/'),
            'kartu' => KartuStok::route('/{record}/kartu'),
        ];
    }
}
