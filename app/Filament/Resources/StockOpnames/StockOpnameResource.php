<?php

namespace App\Filament\Resources\StockOpnames;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\StockOpnames\Pages\CreateStockOpname;
use App\Filament\Resources\StockOpnames\Pages\EditStockOpname;
use App\Filament\Resources\StockOpnames\Pages\LembarHitung;
use App\Filament\Resources\StockOpnames\Pages\ListStockOpnames;
use App\Filament\Resources\StockOpnames\Schemas\StockOpnameForm;
use App\Filament\Resources\StockOpnames\Tables\StockOpnamesTable;
use App\Models\StockOpname;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StockOpnameResource extends BaseResource
{
    protected static ?string $model = StockOpname::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Stok Opname';

    protected static ?string $pluralModelLabel = 'Stok Opname';

    public static function getNavigationGroup(): ?string
    {
        return 'Aset';
    }

    public static function form(Schema $schema): Schema
    {
        return StockOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockOpnamesTable::configure($table);
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
            'index' => ListStockOpnames::route('/'),
            'create' => CreateStockOpname::route('/create'),
            'edit' => EditStockOpname::route('/{record}/edit'),
            'lembar-hitung' => LembarHitung::route('/{record}/lembar-hitung'),
            // Tanpa sesi: kertas kosong yang dibawa lebih dulu ke gudang.
            'lembar-kosong' => LembarHitung::route('/lembar-hitung'),
        ];
    }
}
