<?php

namespace App\Filament\Resources\Machines;

use App\Filament\Resources\Machines\Pages\CreateMachine;
use App\Filament\Resources\Machines\Pages\EditMachine;
use App\Filament\Resources\Machines\Pages\ListMachines;
use App\Filament\Resources\Machines\Schemas\MachineForm;
use App\Filament\Resources\Machines\Tables\MachinesTable;
use App\Models\Machine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog8Tooth;

    protected static ?int $navigationSort = 60;

    protected static ?string $modelLabel = 'Mesin';

    protected static ?string $pluralModelLabel = 'Mesin';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return 'Master Produksi';
    }

    public static function form(Schema $schema): Schema
    {
        return MachineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MachinesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMachines::route('/'),
            'create' => CreateMachine::route('/create'),
            'edit' => EditMachine::route('/{record}/edit'),
        ];
    }
}
