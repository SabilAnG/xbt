<?php

namespace App\Filament\Resources\ProductionServices\Schemas;

use App\Support\MasterDataForm;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductionServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Nama Jasa',
            hint: 'Contoh: Chrome, Poles, Las Argon, Bending.',
            extra: [
                TextInput::make('unit')
                    ->label('Satuan Tarif')
                    ->required()->maxLength(255)
                    ->default('unit')
                    ->placeholder('unit')
                    ->helperText('Tarifnya dihitung per apa: unit, titik, set, pcs, batang.'),

                TextInput::make('rate')
                    ->label('Tarif per Satuan')
                    ->numeric()->minValue(0)->required()
                    ->default(0)
                    ->prefix('Rp')
                    ->helperText('Yang dibayar untuk satu satuan di atas. Chrome Rp150.000 per unit, las Rp5.000 per titik.'),
            ],
        );
    }
}
