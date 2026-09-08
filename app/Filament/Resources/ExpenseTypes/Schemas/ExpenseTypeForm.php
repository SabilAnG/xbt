<?php

namespace App\Filament\Resources\ExpenseTypes\Schemas;

use App\Support\MasterDataForm;
use Filament\Schemas\Schema;

class ExpenseTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Jenis Pengeluaran',
            hint: 'Contoh: Jasa, Barang, Operasional, Gaji.',
        );
    }
}
