<?php

namespace App\Filament\Resources\ItemCategories\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class ItemCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Kategori Produk',
            hint: 'Contoh: Full Set, Silincer, Leheran.',
        );
    }
}
