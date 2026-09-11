<?php

namespace App\Filament\Resources\ProductionServices;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\ProductionServices\Pages\ListProductionServices;
use App\Filament\Resources\ProductionServices\Schemas\ProductionServiceForm;
use App\Filament\Resources\ProductionServices\Tables\ProductionServicesTable;
use App\Models\ProductionService;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Ditaruh tepat setelah Formula karena keduanya sepasang: formula menghitung
 * modal bahan, jasa produksi melengkapinya dengan ongkos kerja.
 */
class ProductionServiceResource extends BaseResource
{
    protected static ?string $model = ProductionService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'Jasa Produksi';

    protected static ?string $pluralModelLabel = 'Jasa Produksi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductionServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionServicesTable::configure($table);
    }

    /**
     * Tanpa halaman tambah dan ubah: isian sependek ini dikerjakan lewat modal,
     * sama seperti master data lainnya.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProductionServices::route('/'),
        ];
    }
}
