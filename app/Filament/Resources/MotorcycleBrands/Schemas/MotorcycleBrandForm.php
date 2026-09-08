<?php

namespace App\Filament\Resources\MotorcycleBrands\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class MotorcycleBrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Brand Motor',
            hint: 'Contoh: Honda, Yamaha, Harley-Davidson.',
            withDescription: false,
        );
    }
}
