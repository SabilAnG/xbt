<?php

namespace App\Filament\Resources\ItemTypes\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class ItemTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Produk',
            hint: 'Contoh: Knalpot Racing, Standar, Standar Racing.',
        );
    }
}
