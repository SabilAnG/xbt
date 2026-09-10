<?php

namespace App\Filament\Resources\ExhaustComponents;

use App\Filament\Resources\ExhaustComponents\Pages\CreateExhaustComponent;
use App\Filament\Resources\ExhaustComponents\Pages\EditExhaustComponent;
use App\Filament\Resources\ExhaustComponents\Pages\IsiBagian;
use App\Filament\Resources\ExhaustComponents\Pages\ListExhaustComponents;
use App\Filament\Resources\ExhaustComponents\Schemas\ExhaustComponentForm;
use App\Filament\Resources\ExhaustComponents\Tables\ExhaustComponentsTable;
use App\Models\ExhaustComponent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExhaustComponentResource extends Resource
{
    protected static ?string $model = ExhaustComponent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'Komponen Knalpot';

    protected static ?string $pluralModelLabel = 'Komponen Knalpot';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    /** Komponen yang bahan bakunya belum dipilih — pekerjaan yang belum selesai. */
    public static function getNavigationBadge(): ?string
    {
        $belum = static::getModel()::query()->tanpaBahan()->where('is_active', true)->count();

        return $belum > 0 ? (string) $belum : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return ExhaustComponentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExhaustComponentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExhaustComponents::route('/'),
            'create' => CreateExhaustComponent::route('/create'),
            // Dibuka dengan mengklik kartu bagiannya.
            'isi' => IsiBagian::route('/{record}/isi'),
            'edit' => EditExhaustComponent::route('/{record}/edit'),
        ];
    }
}
