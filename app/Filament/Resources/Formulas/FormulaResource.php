<?php

namespace App\Filament\Resources\Formulas;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Formulas\Pages\CreateFormula;
use App\Filament\Resources\Formulas\Pages\EditFormula;
use App\Filament\Resources\Formulas\Pages\ListFormulas;
use App\Filament\Resources\Formulas\Schemas\FormulaForm;
use App\Filament\Resources\Formulas\Tables\FormulasTable;
use App\Models\Formula;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FormulaResource extends BaseResource
{
    protected static ?string $model = Formula::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'Formula';

    protected static ?string $pluralModelLabel = 'Formula';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return FormulaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FormulasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormulas::route('/'),
            'create' => CreateFormula::route('/create'),
            'edit' => EditFormula::route('/{record}/edit'),
        ];
    }
}
