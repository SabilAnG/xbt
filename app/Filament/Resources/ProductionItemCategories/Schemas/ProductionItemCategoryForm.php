<?php

namespace App\Filament\Resources\ProductionItemCategories\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class ProductionItemCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Jenis Barang Produksi',
            hint: 'Contoh: Pipa, Plat, Hardware, Bahan Penolong.',
        );
    }
}
