<?php

namespace App\Filament\Resources\MaterialCategories\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class MaterialCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Kategori Bahan',
            hint: 'Contoh: Pipa, Plat, Inlet, Baut, Bahan Poles.',
        );
    }
}
