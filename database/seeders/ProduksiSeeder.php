<?php

namespace Database\Seeders;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\Machine;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MotorcycleModel;
use App\Models\OverheadItem;
use App\Models\PriceTier;
use App\Models\Rack;
use App\Models\Setting;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Isi awal modul inventory produksi.
 *
 * Bahan didaftarkan lengkap dengan dimensi satuan belinya, sehingga harga per
 * satuan pakai (Rp/mm, Rp/mm², Rp/gram) diturunkan sendiri oleh sistem.
 *
 * Formula contoh mengikuti spesifikasi Mio M3 STD Racing. Angkanya adalah
 * TITIK AWAL yang bisa diedit — kalau hasil uji ternyata header Ø28 perlu
 * 135 mm, ubah formulanya, bukan programnya.
 *
 * Idempoten: dicocokkan lewat SKU/slug, aman dijalankan ulang. Baris yang
 * sudah ada TIDAK ditimpa — harga bahan, tarif upah, dan margin yang sudah
 * Anda sesuaikan tetap utuh walau seeder dijalankan lagi.
 */
class ProduksiSeeder extends Seeder
{
    public function run(): void
    {
        $this->kategoriBahan();
        $this->gudangDanRak();
        $this->komponenBiaya();
        $this->mesin();
        $this->vendor();
        $this->bahan();
        $this->overheadBulanan();
        $this->tingkatanHarga();
        $this->formulaMioM3();
    }

    /**
     * Biaya tetap bulanan bengkel.
     *
     * Angka ini yang paling sering berbeda antar bengkel — anggap sebagai
     * contoh dan sesuaikan dengan tagihan Anda sendiri.
     */
    private function overheadBulanan(): void
    {
        $rows = [
            ['Sewa Bengkel', 2_500_000, 'Sewa tempat per bulan'],
            ['Listrik Penerangan & Kantor', 500_000, 'Di luar listrik mesin, yang sudah dihitung per jam mesin'],
            ['Air & Kebersihan', 150_000, ''],
            ['Internet & Telepon', 300_000, ''],
            ['Gaji Admin', 2_000_000, 'Tenaga non-produksi'],
            ['Penyusutan Alat Kecil', 250_000, 'Kunci, tang, klem, meja kerja'],
            ['Lain-lain', 300_000, 'Konsumsi, ATK, perawatan tempat'],
        ];

        foreach ($rows as $i => [$name, $biaya, $catatan]) {
            OverheadItem::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'monthly_cost' => $biaya,
                    'notes' => $catatan ?: null,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Target produksi sebulan — pembagi overhead. Makin banyak yang jadi,
        // makin ringan beban tetap yang ditanggung tiap knalpot.
        if (Setting::get('produksi.target_produksi_bulanan') === null) {
            Setting::put('produksi.target_produksi_bulanan', 50);
        }

        if (Setting::get('produksi.pembulatan_harga') === null) {
            Setting::put('produksi.pembulatan_harga', 1000);
        }

        // Lebar mata potong gerinda; berpengaruh ke berapa potongan yang muat
        // dalam satu lembar plat.
        if (Setting::get('produksi.kerf_mm') === null) {
            Setting::put('produksi.kerf_mm', 3);
        }
    }

    /**
     * Tingkatan harga jual.
     *
     * Margin dihitung atas harga jual, jadi 40% berarti empat puluh persen dari
     * uang yang masuk — bukan modal dikali 1,4.
     */
    private function tingkatanHarga(): void
    {
        $rows = [
            ['Reseller', 20, 0, 'Ambil banyak, harga paling bawah'],
            ['Toko / Grosir', 30, 0, 'Toko variasi yang menjual lagi'],
            ['Retail / Normal', 40, 0, 'Pembeli langsung di bengkel'],
            ['Marketplace', 40, 12, 'Margin sama, tapi harga dinaikkan untuk menutup potongan admin'],
        ];

        foreach ($rows as $i => [$name, $margin, $fee, $catatan]) {
            PriceTier::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'margin_percent' => $margin,
                    'fee_percent' => $fee,
                    'notes' => $catatan,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    private function kategoriBahan(): void
    {
        $rows = [
            ['Pipa', 'Pipa stainless untuk header dan cone'],
            ['Plat', 'Lembaran untuk body, endcap, flange, bracket'],
            ['Core & Packing', 'Perforated core, glass wool, DB killer'],
            ['Hardware', 'Pegas, baut, karet mounting'],
            ['Consumable', 'Kawat las, mata gerinda, amplas, compound'],
        ];

        foreach ($rows as $i => [$name, $desc]) {
            MaterialCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'sort_order' => $i + 1]
            );
        }
    }

    private function gudangDanRak(): void
    {
        $peta = [
            'Gudang Komponen' => [
                ['A1', 'Rak Pipa'],
                ['A2', 'Rak Plat'],
                ['A3', 'Rak Core & Packing'],
                ['A4', 'Rak Hardware'],
            ],
            'Gudang Finishing' => [
                ['B1', 'Rak Barang Setengah Jadi'],
                ['B2', 'Rak Siap Kirim'],
            ],
        ];

        foreach (array_keys($peta) as $i => $namaGudang) {
            $gudang = Warehouse::firstOrCreate(
                ['code' => strtoupper(Str::slug($namaGudang))],
                ['name' => $namaGudang, 'sort_order' => $i + 1]
            );

            foreach ($peta[$namaGudang] as [$kode, $nama]) {
                Rack::firstOrCreate(
                    ['warehouse_id' => $gudang->id, 'code' => $kode],
                    ['name' => $nama]
                );
            }
        }
    }

    /** Upah tenaga kerja per jam — dipakai semua proses. */
    private const UPAH_PER_JAM = 25000;

    /**
     * Proses kerja, bertarif per jam. Formula mengisi menitnya, jadi Anda
     * cukup tahu "potong pipa 10 menit" tanpa menghitung tarif per unit.
     */
    private function komponenBiaya(): void
    {
        // nama, menit standar
        $rows = [
            ['Potong Pipa', 10],
            ['Bending Pipa', 15],
            ['Pembuatan Cone', 10],
            ['Potong Plat', 10],
            ['Roll Body', 10],
            ['Las Header', 15],
            ['Las Silencer', 20],
            ['Pasang Packing', 10],
            ['Gerinda', 15],
            ['Poles', 20],
            ['Assembly', 10],
            ['QC', 5],
        ];

        foreach ($rows as $i => [$name, $menit]) {
            $komponen = CostComponent::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'type' => 'tenaga_kerja',
                    'rate_type' => 'per_hour',
                    'unit' => 'jam',
                    'rate' => self::UPAH_PER_JAM,
                    'default_minutes' => $menit,
                    'description' => $menit.' menit @ Rp'.number_format(self::UPAH_PER_JAM, 0, ',', '.').'/jam',
                    'sort_order' => $i + 1,
                ]
            );

            $this->peringatkanBentrok(
                'Komponen biaya '.Str::slug($name), $komponen->name, $name,
                $komponen->rate_type, 'per_hour'
            );
        }
    }

    /**
     * Baris yang sudah ada dipertahankan apa adanya, tapi kalau bentuknya
     * berbeda dari yang dimaksud seeder, itu tanda kode/SKU-nya bentrok dengan
     * data lain. Diam saja di sini berarti angka salah baru ketahuan setelah
     * dipakai menghitung.
     */
    private function peringatkanBentrok(
        string $kunci,
        string $namaAda,
        string $namaMaksud,
        ?string $bentukAda,
        string $bentukMaksud,
    ): void {
        if ($bentukAda === $bentukMaksud) {
            return;
        }

        $this->command?->warn(sprintf(
            '%s sudah dipakai "%s" (%s), bukan "%s" (%s) — baris lama dipertahankan. '
            .'Ganti kode salah satunya agar tidak tertukar.',
            $kunci, $namaAda, $bentukAda ?: '-', $namaMaksud, $bentukMaksud
        ));
    }

    /**
     * Mesin produksi. Biaya per jam diturunkan dari harga, umur ekonomis,
     * jam pakai, daya listrik, dan maintenance — bukan diketik langsung.
     */
    private function mesin(): void
    {
        // Tarif listrik dipakai semua mesin; bisa diubah di menu pengaturan.
        Setting::firstOrCreate(
            ['key' => 'produksi.tarif_listrik'],
            ['value' => 1500, 'type' => 'text', 'group' => 'produksi']
        );

        // kode, nama, harga, umur thn, jam/thn, kW, maintenance/thn
        $rows = [
            ['LAS', 'Mesin Las TIG', 8000000, 5, 800, 2.5, 1000000],
            ['GERINDA', 'Mesin Gerinda Tangan', 800000, 3, 600, 0.9, 200000],
            ['BENDING', 'Mesin Bending Pipa', 15000000, 8, 400, 1.5, 800000],
            ['ROLL', 'Mesin Roll Plat', 12000000, 8, 300, 1.1, 600000],
            ['POLES', 'Mesin Poles', 1500000, 4, 500, 1.2, 300000],
            ['KOMPRESOR', 'Kompresor Angin', 4000000, 6, 700, 2.2, 400000],
        ];

        foreach ($rows as $i => [$code, $name, $harga, $umur, $jam, $kw, $maint]) {
            Machine::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'purchase_price' => $harga,
                    'economic_life_years' => $umur,
                    'hours_per_year' => $jam,
                    'power_kw' => $kw,
                    'maintenance_per_year' => $maint,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }

    private function vendor(): void
    {
        foreach ([
            ['Toko Logam Jaya', '628123456789'],
            ['Supplier Stainless Nusantara', '628987654321'],
        ] as [$name, $phone]) {
            Vendor::firstOrCreate(['name' => $name], ['phone' => $phone]);
        }
    }

    /**
     * Bahan beserta dimensi satuan belinya.
     *
     * Kolom dimensi inilah yang membuat sistem tahu bahwa satu batang pipa
     * berisi 6.000 mm, sehingga Rp150.000/batang menjadi Rp25/mm.
     */
    private function bahan(): void
    {
        $kat = fn (string $slug) => MaterialCategory::where('slug', $slug)->value('id');

        $rows = [
            // sku, nama, kategori, tipe, satuan beli, harga, dimensi..., min stok (satuan dasar)
            ['PIPA-28', 'Pipa SS 201 Ø28 x 1,2mm', 'pipa', 'linear', 'batang', 150000,
                ['length_mm' => 6000, 'diameter_mm' => 28, 'thickness_mm' => 1.2], 12000],

            ['PIPA-32', 'Pipa SS 201 Ø32 x 1,2mm', 'pipa', 'linear', 'batang', 170000,
                ['length_mm' => 6000, 'diameter_mm' => 32, 'thickness_mm' => 1.2], 12000],

            ['PIPA-38', 'Pipa SS 201 Ø38 x 1,2mm', 'pipa', 'linear', 'batang', 195000,
                ['length_mm' => 6000, 'diameter_mm' => 38, 'thickness_mm' => 1.2], 12000],

            ['PIPA-CONE', 'Pipa Cone Ø28→Ø38', 'pipa', 'linear', 'batang', 210000,
                ['length_mm' => 6000, 'thickness_mm' => 1.2], 6000],

            ['PLAT-08', 'Plat SS 304 0,8mm', 'plat', 'sheet', 'lembar', 600000,
                ['sheet_length_mm' => 2400, 'sheet_width_mm' => 1200, 'thickness_mm' => 0.8], 1000000],

            ['PLAT-12', 'Plat SS 304 1,2mm', 'plat', 'sheet', 'lembar', 850000,
                ['sheet_length_mm' => 2400, 'sheet_width_mm' => 1200, 'thickness_mm' => 1.2], 500000],

            ['PLAT-30', 'Plat SS 3mm (bracket)', 'plat', 'sheet', 'lembar', 2100000,
                ['sheet_length_mm' => 2400, 'sheet_width_mm' => 1200, 'thickness_mm' => 3], 200000],

            ['PLAT-50', 'Plat SS 5mm (flange)', 'plat', 'sheet', 'lembar', 3400000,
                ['sheet_length_mm' => 2400, 'sheet_width_mm' => 1200, 'thickness_mm' => 5], 200000],

            ['CORE-30', 'Perforated Core Ø30 x 300mm', 'core-packing', 'count', 'pcs', 35000, [], 10],
            ['GLASSWOOL', 'Glass Wool Tahan Panas', 'core-packing', 'weight', 'kg', 85000,
                ['weight_gram' => 1000], 3000],
            ['DBKILLER', 'DB Killer Ø28', 'core-packing', 'count', 'pcs', 25000, [], 10],

            ['PEGAS', 'Pegas Knalpot 70mm', 'hardware', 'count', 'pcs', 3500, [], 40],
            ['BAUT-SET', 'Baut & Mur Set', 'hardware', 'count', 'set', 12000, [], 20],
            ['KARET-MNT', 'Karet Mounting Tahan Panas', 'hardware', 'count', 'pcs', 8000, [], 20],

            // Bahan penolong. Tidak menempel di produk tapi habis terpakai —
            // dan di bengkel knalpot jumlahnya tidak kecil.
            ['KAWAT-LAS', 'Kawat Las TIG 1,6mm', 'consumable', 'weight', 'kg', 180000,
                ['weight_gram' => 1000], 2000],
            ['GAS-ARGON', 'Gas Argon', 'consumable', 'volume', 'tabung', 850000,
                ['volume_ml' => 6000000], 1],
            ['MATA-GERINDA', 'Mata Gerinda Potong 4"', 'consumable', 'count', 'pcs', 8000, [], 20],
            ['AMPLAS', 'Amplas Roll', 'consumable', 'count', 'lembar', 5000, [], 30],
            ['COMPOUND', 'Compound Poles', 'consumable', 'weight', 'kg', 95000,
                ['weight_gram' => 1000], 1000],
        ];

        foreach ($rows as [$sku, $name, $katSlug, $tipe, $unit, $harga, $dim, $min]) {
            $bahan = Material::firstOrCreate(
                ['sku' => $sku],
                array_merge([
                    'name' => $name,
                    'material_category_id' => $kat($katSlug),
                    'dimension_type' => $tipe,
                    'unit' => $unit,
                    'cost_price' => $harga,
                    'min_stock' => $min,
                ], $dim)
            );

            // SKU yang sudah dipakai barang lain akan diam-diam terpakai ulang,
            // dan formula jadi merujuk bahan yang salah — pernah terjadi di
            // server, HPP membengkak jadi miliaran karena pipa dianggap "pcs".
            $this->peringatkanBentrok(
                "Bahan SKU {$sku}", $bahan->name, $name,
                $bahan->dimension_type, $tipe
            );
        }
    }

    /**
     * Formula Mio M3 STD Racing.
     *
     * Kebutuhan ditulis dalam SATUAN DASAR:
     *   pipa  -> mm        (header Ø28 sepanjang 150 mm)
     *   plat  -> mm²       (body 324 x 320 = 103.680 mm²)
     *   curah -> gram      (glass wool 0,3 kg = 300 gram)
     *   pcs   -> pcs
     */
    private function formulaMioM3(): void
    {
        $formula = Formula::firstOrCreate(
            ['code' => 'M3-STD-RACING-V1'],
            [
                'name' => 'Mio M3 125 — STD Racing V1',
                'motorcycle_model_id' => MotorcycleModel::where('slug', 'yamaha-mio')->value('id'),
                'output_qty' => 1,
                'output_unit' => 'set',
                'notes' => 'Titik awal dari spesifikasi bengkel. Silakan sesuaikan panjang pipa, '
                    .'ukuran potongan plat, dan waste setelah uji produksi. Pemakaian bahan '
                    .'penolong (kawat las, argon, amplas) adalah perkiraan — timbang sekali '
                    .'saat produksi untuk mendapat angka bengkel Anda sendiri.',
            ]
        );

        if ($formula->materials()->exists()) {
            $this->command?->info('Formula contoh sudah ada, isinya tidak ditimpa.');

            return;
        }

        $bahan = fn (string $sku) => Material::where('sku', $sku)->value('id');
        $biaya = fn (string $slug) => CostComponent::where('slug', $slug)->value('id');

        // Ukuran ditulis apa adanya; luas dan kebutuhan diturunkan sistem.
        // grup, sku, mode, [panjang, lebar, diameter], potong, waste, catatan
        $lines = [
            ['header', 'PIPA-28', 'length', [150, null, null], 1, 10, 'Header 1 — Ø28'],
            ['header', 'PIPA-32', 'length', [220, null, null], 1, 10, 'Header 2 — Ø32'],
            ['header', 'PIPA-38', 'length', [210, null, null], 1, 10, 'Header 3 — Ø38'],
            ['header', 'PIPA-CONE', 'length', [110, null, null], 1, 10, 'Cone Ø28→Ø38'],

            ['silencer', 'PLAT-08', 'rect', [324, 320, null], 1, 3, 'Body silencer'],
            ['silencer', 'PLAT-12', 'circle', [null, null, 110], 2, 3, 'Endcap Ø110'],
            ['silencer', 'CORE-30', 'direct', [null, null, null], 1, 5, 'Perforated core Ø30 x 300'],
            ['silencer', 'GLASSWOOL', 'direct', [null, null, null], 1, 10, '0,3 kg'],
            ['silencer', 'DBKILLER', 'direct', [null, null, null], 1, 5, ''],

            ['mounting', 'PLAT-50', 'rect', [80, 80, null], 1, 3, 'Flange'],
            ['mounting', 'PLAT-30', 'rect', [100, 40, null], 1, 3, 'Bracket'],
            ['mounting', 'PEGAS', 'direct', [null, null, null], 1, 5, ''],
            ['mounting', 'BAUT-SET', 'direct', [null, null, null], 1, 5, ''],
            ['mounting', 'KARET-MNT', 'direct', [null, null, null], 1, 5, ''],

            // Bahan penolong: tidak menempel di produk tapi tetap habis.
            // Di knalpot porsinya tidak kecil — kalau tidak dicatat, HPP
            // terlihat lebih murah daripada kenyataan.
            ['consumable', 'KAWAT-LAS', 'direct', [null, null, null], 1, 0, 'Kawat las TIG'],
            ['consumable', 'GAS-ARGON', 'direct', [null, null, null], 1, 0, 'Argon, sekitar 15 menit nyala'],
            ['consumable', 'MATA-GERINDA', 'direct', [null, null, null], 1, 0, 'Satu mata untuk 4 knalpot'],
            ['consumable', 'AMPLAS', 'direct', [null, null, null], 1, 0, ''],
            ['consumable', 'COMPOUND', 'direct', [null, null, null], 1, 0, 'Compound poles'],
        ];

        // Bahan bermode `direct` diisi jumlahnya di satuan dasar.
        $qtyLangsung = ['CORE-30' => 1, 'GLASSWOOL' => 300, 'DBKILLER' => 1,
            'PEGAS' => 2, 'BAUT-SET' => 1, 'KARET-MNT' => 2,
            // penolong, dalam satuan dasar: gram, ml, pcs, lembar
            'KAWAT-LAS' => 60, 'GAS-ARGON' => 150_000, 'MATA-GERINDA' => 0.25,
            'AMPLAS' => 2, 'COMPOUND' => 30];

        foreach ($lines as $i => [$grup, $sku, $mode, $dim, $potong, $waste, $note]) {
            $formula->materials()->create([
                'material_id' => $bahan($sku),
                'bom_group' => $grup,
                'input_mode' => $mode,
                'piece_length_mm' => $dim[0],
                'piece_width_mm' => $dim[1],
                'piece_diameter_mm' => $dim[2],
                'piece_count' => $potong,
                'qty' => $qtyLangsung[$sku] ?? 0,   // dipakai hanya bila mode direct
                'waste_percent' => $waste,
                'notes' => $note,
                'sort_order' => $i + 1,
            ]);
        }

        // Susut plat sengaja kecil: sisa potong sudah dihitung sendiri lewat
        // nesting, jadi angka di sini hanya untuk potongan gagal yang harus
        // diulang. Dulu 5-10% karena nesting belum ada.

        // Waktu kerja per proses — total 150 menit (2,5 jam).
        $jasa = [
            ['header', 'potong-pipa', 10],
            ['header', 'bending-pipa', 15],
            ['header', 'pembuatan-cone', 10],
            ['header', 'las-header', 15],
            ['silencer', 'potong-plat', 10],
            ['silencer', 'roll-body', 10],
            ['silencer', 'las-silencer', 20],
            ['silencer', 'pasang-packing', 10],
            ['finishing', 'gerinda', 15],
            ['finishing', 'poles', 20],
            ['finishing', 'assembly', 10],
            ['finishing', 'qc', 5],
        ];

        foreach ($jasa as $i => [$grup, $slug, $menit]) {
            $formula->costs()->create([
                'cost_component_id' => $biaya($slug),
                'bom_group' => $grup,
                'qty' => 1,
                'minutes' => $menit,
                'sort_order' => $i + 1,
            ]);
        }

        // Pemakaian mesin, juga dalam menit.
        $mesin = fn (string $code) => Machine::where('code', $code)->value('id');

        $pakaiMesin = [
            ['header', 'BENDING', 15],
            ['header', 'LAS', 15],
            ['silencer', 'ROLL', 10],
            ['silencer', 'LAS', 20],
            ['finishing', 'GERINDA', 15],
            ['finishing', 'POLES', 20],
            ['finishing', 'KOMPRESOR', 10],
        ];

        // Satu mesin hanya boleh sekali per formula, jadi menitnya dijumlah.
        $totalMenit = [];
        foreach ($pakaiMesin as [$grup, $code, $menit]) {
            $totalMenit[$code]['grup'] ??= $grup;
            $totalMenit[$code]['menit'] = ($totalMenit[$code]['menit'] ?? 0) + $menit;
        }

        $i = 0;
        foreach ($totalMenit as $code => $isi) {
            $formula->machines()->create([
                'machine_id' => $mesin($code),
                'bom_group' => $isi['grup'],
                'minutes' => $isi['menit'],
                'sort_order' => ++$i,
            ]);
        }

        $formula->load(['materials.material', 'costs.component', 'machines.machine']);
        $b = $formula->breakdown();

        $rp = fn (float $n) => number_format($n, 0, ',', '.');

        $this->command?->info(sprintf(
            'Formula Mio M3 STD Racing: bahan Rp%s + tenaga kerja Rp%s (%s menit) + mesin Rp%s = HPP Rp%s/set',
            $rp($b['bahan']), $rp($b['jasa']), $rp($b['menit']), $rp($b['mesin']), $rp($b['per_unit'])
        ));
    }
}
