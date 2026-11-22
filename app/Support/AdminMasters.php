<?php

namespace App\Support;

use App\Models\CostComponent;
use App\Models\Machine;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\OverheadItem;
use App\Models\PriceTier;
use Illuminate\Database\Eloquent\Model;

/**
 * Definisi master data sederhana untuk MasterController: isian form, kolom
 * tabel, dan relasi yang menghalangi penghapusan. Satu tempat supaya master
 * baru cukup ditambah di sini, bukan menulis controller + view baru.
 */
class AdminMasters
{
    /**
     * @return array<string, array{
     *     model: class-string<Model>, title: string, singular: string, subtitle: string,
     *     slug_column?: string, order: string,
     *     fields: array<string, array<string, mixed>>,
     *     columns: array<string, callable(Model): string>,
     *     in_use?: array<string, string>
     * }>
     */
    public static function all(): array
    {
        $rp = fn ($value) => rupiah($value);
        $percent = fn ($value) => qty($value, 2).'%';

        return [
            'komponen-biaya' => [
                'model' => CostComponent::class,
                'title' => 'Komponen Biaya',
                'singular' => 'komponen biaya',
                'subtitle' => 'Proses kerja & jasa yang dipakai di formula: tarif per jam (isi menit) atau borongan per unit.',
                'slug_column' => 'slug',
                'order' => 'sort_order',
                'fields' => [
                    'name' => ['label' => 'Nama proses / jasa', 'type' => 'text', 'required' => true, 'span' => 2],
                    'type' => ['label' => 'Jenis', 'type' => 'select', 'options' => CostComponent::TYPES, 'required' => true],
                    'rate_type' => ['label' => 'Cara hitung', 'type' => 'select', 'options' => CostComponent::RATE_TYPES, 'required' => true],
                    'rate' => ['label' => 'Tarif (Rp)', 'type' => 'number', 'required' => true, 'hint' => 'Per jam bila "Per jam", per unit bila borongan.'],
                    'unit' => ['label' => 'Satuan borongan', 'type' => 'text', 'default' => 'unit'],
                    'default_minutes' => ['label' => 'Menit standar', 'type' => 'number', 'hint' => 'Isian awal saat proses ini ditambahkan ke formula.'],
                    'sort_order' => ['label' => 'Urutan', 'type' => 'number', 'default' => 0],
                    'description' => ['label' => 'Keterangan', 'type' => 'text', 'span' => 3],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Nama' => fn (CostComponent $row) => $row->name,
                    'Jenis' => fn (CostComponent $row) => CostComponent::TYPES[$row->type] ?? $row->type,
                    'Tarif' => fn (CostComponent $row) => $row->displayRate(),
                    'Menit standar' => fn (CostComponent $row) => $row->isHourly() ? qty($row->default_minutes, 2).' menit' : '—',
                ],
                'in_use' => ['formulaLines' => 'formula'],
            ],
            'mesin' => [
                'model' => Machine::class,
                'title' => 'Mesin',
                'singular' => 'mesin',
                'subtitle' => 'Biaya mesin per jam dihitung dari harga, umur ekonomis, jam pakai, daya listrik, dan perawatan.',
                'order' => 'sort_order',
                'fields' => [
                    'name' => ['label' => 'Nama mesin', 'type' => 'text', 'required' => true, 'span' => 2],
                    'code' => ['label' => 'Kode', 'type' => 'text', 'required' => true, 'unique' => true],
                    'purchase_price' => ['label' => 'Harga beli (Rp)', 'type' => 'number', 'required' => true],
                    'economic_life_years' => ['label' => 'Umur ekonomis (tahun)', 'type' => 'number', 'required' => true, 'default' => 5],
                    'hours_per_year' => ['label' => 'Jam pakai per tahun', 'type' => 'number', 'required' => true, 'default' => 1000],
                    'power_kw' => ['label' => 'Daya (kW)', 'type' => 'number', 'default' => 0],
                    'maintenance_per_year' => ['label' => 'Perawatan per tahun (Rp)', 'type' => 'number', 'default' => 0],
                    'sort_order' => ['label' => 'Urutan', 'type' => 'number', 'default' => 0],
                    'description' => ['label' => 'Keterangan', 'type' => 'text', 'span' => 3],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Kode' => fn (Machine $row) => $row->code,
                    'Nama' => fn (Machine $row) => $row->name,
                    'Penyusutan/jam' => fn (Machine $row) => $rp($row->depreciationPerHour()),
                    'Listrik/jam' => fn (Machine $row) => $rp($row->electricityPerHour()),
                    'Perawatan/jam' => fn (Machine $row) => $rp($row->maintenancePerHour()),
                    'Biaya/jam' => fn (Machine $row) => $row->displayHourlyCost(),
                ],
                'in_use' => ['formulaLines' => 'formula'],
            ],
            'overhead' => [
                'model' => OverheadItem::class,
                'title' => 'Overhead Bulanan',
                'singular' => 'biaya overhead',
                'subtitle' => 'Biaya tetap bengkel per bulan. Dibagi target produksi bulanan (Pengaturan Produksi) menjadi beban per unit.',
                'slug_column' => 'slug',
                'order' => 'sort_order',
                'fields' => [
                    'name' => ['label' => 'Nama biaya', 'type' => 'text', 'required' => true, 'span' => 2],
                    'monthly_cost' => ['label' => 'Biaya per bulan (Rp)', 'type' => 'number', 'required' => true],
                    'sort_order' => ['label' => 'Urutan', 'type' => 'number', 'default' => 0],
                    'notes' => ['label' => 'Catatan', 'type' => 'text', 'span' => 2],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Nama' => fn (OverheadItem $row) => $row->name,
                    'Per bulan' => fn (OverheadItem $row) => $rp($row->monthly_cost),
                    'Catatan' => fn (OverheadItem $row) => (string) $row->notes,
                ],
            ],
            'tingkatan-harga' => [
                'model' => PriceTier::class,
                'title' => 'Tingkatan Harga',
                'singular' => 'tingkatan harga',
                'subtitle' => 'Margin dihitung atas harga jual (40% = 40% dari uang masuk). Potongan = fee marketplace.',
                'slug_column' => 'slug',
                'order' => 'sort_order',
                'fields' => [
                    'name' => ['label' => 'Nama tingkatan', 'type' => 'text', 'required' => true, 'span' => 2],
                    'margin_percent' => ['label' => 'Margin (%)', 'type' => 'number', 'required' => true],
                    'fee_percent' => ['label' => 'Potongan / fee (%)', 'type' => 'number', 'default' => 0],
                    'sort_order' => ['label' => 'Urutan', 'type' => 'number', 'default' => 0],
                    'notes' => ['label' => 'Catatan', 'type' => 'text'],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Nama' => fn (PriceTier $row) => $row->name,
                    'Margin' => fn (PriceTier $row) => $percent($row->margin_percent),
                    'Potongan' => fn (PriceTier $row) => $percent($row->fee_percent),
                    'Catatan' => fn (PriceTier $row) => (string) $row->notes,
                ],
            ],
            'merek-motor' => [
                'model' => MotorcycleBrand::class,
                'title' => 'Merek Motor',
                'singular' => 'merek motor',
                'subtitle' => 'Merek kendaraan (Yamaha, Honda, ...) untuk Type Motor.',
                'slug_column' => 'slug',
                'order' => 'sort_order',
                'fields' => [
                    'name' => ['label' => 'Nama merek', 'type' => 'text', 'required' => true, 'span' => 2],
                    'sort_order' => ['label' => 'Urutan', 'type' => 'number', 'default' => 0],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Nama' => fn (MotorcycleBrand $row) => $row->name,
                ],
                'in_use' => ['models' => 'type motor'],
            ],
            'type-motor' => [
                'model' => MotorcycleModel::class,
                'title' => 'Type Motor',
                'singular' => 'type motor',
                'subtitle' => 'Type kendaraan yang bisa dipilih di formula.',
                'slug_column' => 'slug',
                'order' => 'name',
                'fields' => [
                    'motorcycle_brand_id' => ['label' => 'Merek', 'type' => 'select', 'options' => fn () => MotorcycleBrand::orderBy('name')->pluck('name', 'id')->all(), 'required' => true],
                    'name' => ['label' => 'Nama type', 'type' => 'text', 'required' => true, 'span' => 2],
                    'year_from' => ['label' => 'Tahun dari', 'type' => 'number'],
                    'year_to' => ['label' => 'Tahun sampai', 'type' => 'number'],
                    'is_active' => ['label' => 'Aktif', 'type' => 'checkbox', 'default' => true],
                ],
                'columns' => [
                    'Merek' => fn (MotorcycleModel $row) => (string) $row->brand?->name,
                    'Type' => fn (MotorcycleModel $row) => $row->name,
                    'Tahun' => fn (MotorcycleModel $row) => trim(($row->year_from ?? '').'–'.($row->year_to ?? ''), '–'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $all = static::all();
        abort_unless(isset($all[$key]), 404);

        return $all[$key];
    }
}
