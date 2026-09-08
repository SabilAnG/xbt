<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Isi awal master data inventory. Idempoten — dicocokkan lewat slug, jadi aman
 * dijalankan ulang dan tidak menimpa data yang sudah diubah namanya.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $this->itemCategories();
        $this->itemTypes();
        $this->motorcycles();
        $this->expenses();
        $this->wallets();
    }

    private function itemCategories(): void
    {
        $rows = [
            ['Full Set', 'Satu set knalpot lengkap dari leher sampai silincer'],
            ['Silincer', 'Bagian ujung knalpot'],
            ['Leheran', 'Pipa penghubung dari mesin ke silincer'],
            ['Sparepart', 'Komponen pendukung: baut, packing, dudukan'],
        ];

        foreach ($rows as $i => [$name, $desc]) {
            ItemCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'sort_order' => $i + 1]
            );
        }
    }

    private function itemTypes(): void
    {
        $rows = [
            ['Knalpot Racing', 'Model racing, suara lebih keras'],
            ['Standar', 'Bawaan pabrik'],
            ['Standar Racing', 'Tampilan standar dengan karakter racing'],
        ];

        foreach ($rows as $i => [$name, $desc]) {
            ItemType::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'sort_order' => $i + 1]
            );
        }
    }

    private function motorcycles(): void
    {
        $brands = [
            'Honda' => ['Vario 160', 'Beat', 'CBR 150R', 'Scoopy'],
            'Yamaha' => ['NMAX', 'Aerox 155', 'R15', 'Mio'],
            'Suzuki' => ['Satria F150', 'GSX-R150'],
            'Kawasaki' => ['Ninja 250', 'W175'],
            'Harley-Davidson' => ['Sportster', 'Dyna', 'Touring', 'V-Rod'],
        ];

        foreach (array_keys($brands) as $i => $brandName) {
            $brand = MotorcycleBrand::firstOrCreate(
                ['slug' => Str::slug($brandName)],
                ['name' => $brandName, 'sort_order' => $i + 1]
            );

            foreach ($brands[$brandName] as $modelName) {
                MotorcycleModel::firstOrCreate(
                    ['slug' => Str::slug($brandName.' '.$modelName)],
                    ['motorcycle_brand_id' => $brand->id, 'name' => $modelName]
                );
            }
        }
    }

    private function expenses(): void
    {
        // jenis => daftar kategori di bawahnya
        $tree = [
            'Jasa' => ['Bayar Las', 'Bubut', 'Poles / Finishing', 'Ongkos Kirim'],
            'Barang' => ['Bahan Baku Pipa', 'Bahan Poles', 'Perlengkapan Bengkel'],
            'Operasional' => ['Listrik', 'Air', 'Internet', 'Sewa Tempat'],
            'Gaji' => ['Gaji Karyawan', 'Bonus'],
        ];

        foreach (array_keys($tree) as $i => $typeName) {
            $type = ExpenseType::firstOrCreate(
                ['slug' => Str::slug($typeName)],
                ['name' => $typeName, 'sort_order' => $i + 1]
            );

            foreach ($tree[$typeName] as $catName) {
                ExpenseCategory::firstOrCreate(
                    ['slug' => Str::slug($catName)],
                    ['expense_type_id' => $type->id, 'name' => $catName]
                );
            }
        }
    }

    private function wallets(): void
    {
        $rows = [
            ['Kas Laci', 'cash', null],
            ['Rekening BCA', 'bank', null],
        ];

        foreach ($rows as $i => [$name, $type, $account]) {
            Wallet::firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'account_number' => $account, 'sort_order' => $i + 1]
            );
        }
    }
}
