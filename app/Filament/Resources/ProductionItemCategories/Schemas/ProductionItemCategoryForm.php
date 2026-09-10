<?php

namespace App\Filament\Resources\ProductionItemCategories\Schemas;

use App\Models\ProductionItem;
use App\Models\Warehouse;
use App\Support\MasterDataForm;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ProductionItemCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return MasterDataForm::build(
            $schema,
            nameLabel: 'Jenis Barang',
            hint: 'Contoh: Pipa, Plat, Baut & Mur, Bahan Penolong.',
            extra: [
                // Dijawab sekali di sini, lalu terisi otomatis tiap kali barang
                // dengan jenis ini ditambahkan.
                Select::make('role')
                    ->label('Perannya di produk')
                    ->options(ProductionItem::ROLES)
                    ->default('utama')->required()
                    ->helperText('Bahan utama menempel jadi badan knalpot. Aksesoris Utama untuk yang bentuknya aksesoris tapi wajib ada, seperti pegas dan karet mounting. Penolong seperti kawat las dan amplas — habis dipakai tapi tidak menempel.'),

                Select::make('source')
                    ->label('Didapat dari')
                    ->options(ProductionItem::SOURCES)
                    ->default('beli')->required()
                    ->helperText('Pilih yang ketiga bila jenis ini bisa ditebus di toko maupun dibuat sendiri, seperti cone dan perforated core.'),

                Select::make('default_warehouse_id')
                    ->label('Gudang bawaan')
                    ->options(fn () => Warehouse::query()
                        ->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                    ->searchable()
                    ->default(fn () => Warehouse::where('type', 'bahan_mentah')->value('id'))
                    ->helperText('Tempat barang jenis ini biasanya disimpan. Dipakai sebagai isian awal saat opname dan pembelian — bukan pagar, masih bisa diubah.'),
            ],
        );
    }
}
