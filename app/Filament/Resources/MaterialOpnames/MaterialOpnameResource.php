<?php

namespace App\Filament\Resources\MaterialOpnames;

use App\Filament\Resources\MaterialOpnames\Pages\CreateMaterialOpname;
use App\Filament\Resources\MaterialOpnames\Pages\EditMaterialOpname;
use App\Filament\Resources\MaterialOpnames\Pages\LembarHitung;
use App\Filament\Resources\MaterialOpnames\Pages\ListMaterialOpnames;
use App\Filament\Resources\MaterialOpnames\Schemas\MaterialOpnameForm;
use App\Filament\Resources\MaterialOpnames\Tables\MaterialOpnamesTable;
use App\Models\MaterialOpname;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MaterialOpnameResource extends Resource
{
    protected static ?string $model = MaterialOpname::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'Stok Opname Bahan';

    protected static ?string $pluralModelLabel = 'Stok Opname Bahan';

    protected static ?string $recordTitleAttribute = 'opname_number';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return MaterialOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MaterialOpnamesTable::configure($table);
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
            'index' => ListMaterialOpnames::route('/'),
            'create' => CreateMaterialOpname::route('/create'),
            'edit' => EditMaterialOpname::route('/{record}/edit'),
            'lembar-hitung' => LembarHitung::route('/{record}/lembar-hitung'),
        ];
    }
}
