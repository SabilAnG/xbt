<?php

namespace App\Filament\Resources\ProductionItemOpnames;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ProductionItemOpnames\Pages\CreateProductionItemOpname;
use App\Filament\Resources\ProductionItemOpnames\Pages\EditProductionItemOpname;
use App\Filament\Resources\ProductionItemOpnames\Pages\ListProductionItemOpnames;
use App\Filament\Resources\ProductionItemOpnames\Schemas\ProductionItemOpnameForm;
use App\Filament\Resources\ProductionItemOpnames\Tables\ProductionItemOpnamesTable;
use App\Models\ProductionItemOpname;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductionItemOpnameResource extends BaseResource
{
    protected static ?string $model = ProductionItemOpname::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 30;

    // Menunya memakai kata kerja karena itu yang dicari orang: "saya mau
    // hitung ulang stok". Nama dokumennya tetap kata benda supaya "Buat
    // Hitungan Stok" tetap berbunyi seperti kalimat.
    protected static ?string $navigationLabel = 'Hitung Ulang Stok';

    protected static ?string $modelLabel = 'Hitungan Stok';

    protected static ?string $pluralModelLabel = 'Hitungan Stok';

    protected static ?string $recordTitleAttribute = 'opname_number';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionItemOpnameForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionItemOpnamesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionItemOpnames::route('/'),
            'create' => CreateProductionItemOpname::route('/create'),
            'edit' => EditProductionItemOpname::route('/{record}/edit'),
        ];
    }
}
