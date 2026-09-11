<?php

namespace App\Filament\Resources\Partners;

use App\Filament\Resources\Partners\Pages\ListPartners;
use App\Filament\Resources\Partners\Schemas\PartnerForm;
use App\Filament\Resources\Partners\Tables\PartnersTable;
use App\Models\Tenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Partner yang membuka toko sendiri di atas aplikasi ini.
 *
 * Barisnya lahir dari formulir publik dan diam di situ sampai disetujui —
 * tombol setuju itulah yang membuatkan database, akun, dan subdomainnya.
 */
class PartnerResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Partner';

    protected static ?string $pluralModelLabel = 'Partner';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Partner';
    }

    /** Pendaftar yang menunggu perlu terlihat, bukan dicari. */
    public static function getNavigationBadge(): ?string
    {
        $menunggu = Tenant::menunggu()->count();

        return $menunggu > 0 ? (string) $menunggu : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return PartnerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartners::route('/'),
        ];
    }
}
