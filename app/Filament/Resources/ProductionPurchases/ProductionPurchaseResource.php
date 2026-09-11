<?php

namespace App\Filament\Resources\ProductionPurchases;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ProductionPurchases\Pages\CreateProductionPurchase;
use App\Filament\Resources\ProductionPurchases\Pages\EditProductionPurchase;
use App\Filament\Resources\ProductionPurchases\Pages\ListProductionPurchases;
use App\Filament\Resources\ProductionPurchases\Schemas\ProductionPurchaseForm;
use App\Filament\Resources\ProductionPurchases\Tables\ProductionPurchasesTable;
use App\Models\ProductionPurchase;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Pembelian bahan produksi — pipa, plat, baut, pegas.
 *
 * Berbeda dari menu Pembelian di Operasional, yang membeli barang jual dan
 * menambah Stok Barang. Yang ini menambah stok Barang Produksi, per gudang.
 *
 * Tetap berhalaman penuh, tidak modal: notanya bertingkat dan barisnya banyak,
 * dan mengisinya bukan pekerjaan sekali ketik.
 */
class ProductionPurchaseResource extends BaseResource
{
    protected static ?string $model = ProductionPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Pembelian Bahan';

    protected static ?string $pluralModelLabel = 'Pembelian Bahan';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    /** Nota draft yang belum dibukukan perlu terlihat, bukan dicari. */
    public static function getNavigationBadge(): ?string
    {
        $draft = ProductionPurchase::where('status', 'draft')->count();

        return $draft > 0 ? (string) $draft : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionPurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionPurchasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionPurchases::route('/'),
            'create' => CreateProductionPurchase::route('/create'),
            'edit' => EditProductionPurchase::route('/{record}/edit'),
        ];
    }
}
