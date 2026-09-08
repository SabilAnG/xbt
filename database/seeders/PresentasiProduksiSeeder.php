<?php

namespace Database\Seeders;

use App\Models\CostComponent;
use App\Models\Formula;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemType;
use App\Models\Machine;
use App\Models\Material;
use App\Models\MaterialOpname;
use App\Models\MaterialPurchase;
use App\Models\MotorcycleModel;
use App\Models\PriceTier;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\Rack;
use App\Models\Vendor;
use App\Models\Wallet;
use App\Models\Warehouse;
use App\Services\DocumentNumber;
use App\Services\ProductionPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh untuk presentasi modul produksi.
 *
 * Mengisi yang masih kosong: barang jual, tiga formula tambahan, pembelian
 * bahan, nota produksi, stok opname, dan satu rencana produksi. Master data
 * (bahan, mesin, overhead, tingkatan harga) tidak disentuh — itu sudah diisi
 * ProduksiSeeder.
 *
 * Nomor dokumen yang dibuat dicatat di storage/app/presentasi-produksi.json,
 * sehingga `deploy/hapus-data-presentasi.php` bisa mencabutnya kembali dengan
 * tepat tanpa perlu menandai catatan tiap dokumen — tampilan tetap bersih
 * saat dipresentasikan.
 *
 * Idempoten: kalau manifesnya sudah ada, seeder berhenti dan tidak menggandakan.
 */
class PresentasiProduksiSeeder extends Seeder
{
    public const MANIFEST = 'presentasi-produksi.json';

    /** @var array<string, array<int, string>> */
    private array $dibuat = [
        'formula' => [], 'item' => [], 'pembelian' => [],
        'produksi' => [], 'opname' => [], 'rencana' => [], 'saldo_awal' => [],
    ];

    private ProductionPostingService $posting;

    public function run(): void
    {
        if (Storage::disk('local')->exists(self::MANIFEST)) {
            $this->command?->warn(
                'Data presentasi sudah pernah dibuat. Hapus dulu dengan '
                .'deploy/hapus-data-presentasi.php bila ingin menyusun ulang.'
            );

            return;
        }

        $this->posting = app(ProductionPostingService::class);

        $this->modalUsaha();
        $this->barangJual();
        $this->formulaTambahan();
        $this->pembelianBahan();
        $this->notaProduksi();
        $this->stokOpname();
        $this->rencanaProduksi();

        Storage::disk('local')->put(self::MANIFEST, json_encode([
            'dibuat_pada' => now()->toDateTimeString(),
            'isi' => $this->dibuat,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->ringkasan();
    }

    // ------------------------------------------------------------------ modal

    /**
     * Rekening bank diberi saldo awal supaya pembelian bahan tidak membuat
     * saldonya minus — bengkel yang belanja Rp8 juta memang punya uangnya dulu.
     */
    private function modalUsaha(): void
    {
        $bank = Wallet::where('type', 'bank')->first();

        if ($bank && (float) $bank->opening_balance <= 0 && (float) $bank->current_balance <= 0) {
            // Nilai lamanya dicatat agar bisa dikembalikan saat data presentasi
            // dicabut.
            $this->dibuat['saldo_awal'][] = [
                'wallet_id' => $bank->id,
                'sebelumnya' => (float) $bank->opening_balance,
            ];

            $bank->forceFill(['opening_balance' => 60_000_000])->save();
            $bank->recalculateBalance();
            $this->command?->info('Saldo awal '.$bank->name.' diisi Rp60.000.000 sebagai modal usaha.');
        }
    }

    // ------------------------------------------------------------ barang jual

    /**
     * Barang jual yang dihasilkan tiap formula. Tanpa ini hasil produksi tidak
     * punya tempat masuk dan rantai produksi -> penjualan tidak terlihat.
     */
    private function barangJual(): void
    {
        $kategori = ItemCategory::where('name', 'Full Set')->value('id')
            ?? ItemCategory::value('id');
        $tipe = ItemType::where('name', 'Standar Racing')->value('id')
            ?? ItemType::value('id');

        foreach (self::PRODUK as $sku => [$nama, $kodeFormula]) {
            $item = Item::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $nama,
                    'item_category_id' => $kategori,
                    'item_type_id' => $tipe,
                    'unit' => 'set',
                    'cost_price' => 0,
                    'sell_price' => 0,
                    'stock' => 0,
                    'min_stock' => 2,
                    'is_active' => true,
                ]
            );

            if ($item->wasRecentlyCreated) {
                $this->dibuat['item'][] = $sku;
            }
        }
    }

    /** sku barang jual => [nama, kode formula] */
    private const PRODUK = [
        'KNP-MIO-STD' => ['Knalpot Mio M3 STD Racing', 'M3-STD-RACING-V1'],
        'KNP-BEAT-RC' => ['Knalpot Beat Racing', 'BEAT-RACING-V1'],
        'KNP-NMAX-FS' => ['Knalpot NMAX Full System', 'NMAX-FULLSYS-V1'],
        'KNP-VARIO-BB' => ['Knalpot Vario Bobokan', 'VARIO-BOBOK-V1'],
    ];

    // ---------------------------------------------------------------- formula

    private function formulaTambahan(): void
    {
        foreach (self::FORMULA as $kode => $spek) {
            $formula = Formula::where('code', $kode)->first();

            if (! $formula) {
                $formula = Formula::create([
                    'code' => $kode,
                    'name' => $spek['nama'],
                    'motorcycle_model_id' => MotorcycleModel::where('slug', $spek['motor'])->value('id'),
                    'output_qty' => 1,
                    'output_unit' => 'set',
                    'notes' => $spek['catatan'],
                    'is_active' => true,
                ]);
                $this->dibuat['formula'][] = $kode;

                $this->isiFormula($formula, $spek);
            }

        }

        $this->tautkanBarangJual();
        $this->hargaJualDariHpp();
    }

    /**
     * Tiap formula ditunjukkan barang jual yang dihasilkannya.
     *
     * Dilakukan terpisah dari pembuatan formula supaya formula bawaan — yang
     * tidak ada di daftar FORMULA — ikut tertaut juga.
     */
    private function tautkanBarangJual(): void
    {
        foreach (self::PRODUK as $sku => [$nama, $kodeFormula]) {
            $formula = Formula::where('code', $kodeFormula)->first();
            $item = Item::where('sku', $sku)->first();

            if ($formula && $item && ! $formula->item_id) {
                $formula->forceFill(['item_id' => $item->id])->save();
            }
        }
    }

    /** @param array<string, mixed> $spek */
    private function isiFormula(Formula $formula, array $spek): void
    {
        foreach ($spek['bahan'] as $i => [$grup, $sku, $mode, $dim, $potong, $waste, $qty]) {
            $formula->materials()->create([
                'material_id' => Material::where('sku', $sku)->value('id'),
                'bom_group' => $grup,
                'input_mode' => $mode,
                'piece_length_mm' => $dim[0],
                'piece_width_mm' => $dim[1],
                'piece_diameter_mm' => $dim[2],
                'piece_count' => $potong,
                'qty' => $qty,
                'waste_percent' => $waste,
                'use_nesting' => $mode !== 'direct',
                'sort_order' => $i + 1,
            ]);
        }

        foreach ($spek['proses'] as $i => [$grup, $slug, $menit]) {
            $formula->costs()->create([
                'cost_component_id' => CostComponent::where('slug', $slug)->value('id'),
                'bom_group' => $grup,
                'qty' => 1,
                'minutes' => $menit,
                'sort_order' => $i + 1,
            ]);
        }

        foreach ($spek['mesin'] as $i => [$grup, $kode, $menit]) {
            $formula->machines()->create([
                'machine_id' => Machine::where('code', $kode)->value('id'),
                'bom_group' => $grup,
                'minutes' => $menit,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** Harga jual barang diisi dari tingkatan retail agar konsisten dengan HPP. */
    private function hargaJualDariHpp(): void
    {
        $retail = PriceTier::where('is_active', true)
            ->where('fee_percent', 0)->orderByDesc('margin_percent')->first();

        if (! $retail) {
            return;
        }

        foreach (Formula::with(['materials.material', 'costs.component', 'machines.machine'])
            ->whereNotNull('item_id')->get() as $f) {
            $f->item?->forceFill([
                'sell_price' => $retail->price($f->hppPerUnit()),
            ])->save();
        }
    }

    // -------------------------------------------------------- pembelian bahan

    private function pembelianBahan(): void
    {
        $rak = fn (string $kode) => Rack::where('code', $kode)->value('id');
        $bank = Wallet::where('type', 'bank')->value('id');
        $vendor = Vendor::orderBy('id')->pluck('id')->all();

        foreach (self::BELANJA as $i => [$tanggal, $isi]) {
            $nota = MaterialPurchase::create([
                'invoice_number' => DocumentNumber::next('material_purchases', Carbon::parse($tanggal)),
                'purchased_at' => $tanggal,
                'vendor_id' => $vendor[$i % max(count($vendor), 1)] ?? null,
                'wallet_id' => $bank,
                'status' => 'draft',
            ]);

            foreach ($isi as [$sku, $qty, $kodeRak]) {
                $bahan = Material::where('sku', $sku)->first();

                if (! $bahan) {
                    continue;
                }

                $nota->items()->create([
                    'material_id' => $bahan->id,
                    'rack_id' => $rak($kodeRak),
                    'qty' => $qty,
                    'unit_cost' => (float) $bahan->cost_price,
                ]);
            }

            $nota->recalculateTotals();
            $this->posting->postPurchase($nota->refresh());
            $this->dibuat['pembelian'][] = $nota->invoice_number;
        }
    }

    // ---------------------------------------------------------- nota produksi

    private function notaProduksi(): void
    {
        $gudang = Warehouse::where('name', 'like', '%Finishing%')->value('id')
            ?? Warehouse::value('id');

        foreach (self::PRODUKSI as [$tanggal, $kodeFormula, $batch]) {
            $formula = Formula::where('code', $kodeFormula)->first();

            if (! $formula) {
                continue;
            }

            $nota = Production::create([
                'production_number' => DocumentNumber::next('productions', Carbon::parse($tanggal)),
                'produced_at' => $tanggal,
                'formula_id' => $formula->id,
                'warehouse_id' => $gudang,
                'batch_qty' => $batch,
                'status' => 'draft',
            ]);

            $this->posting->fillFromFormula($nota);
            $this->posting->postProduction($nota->refresh());
            $this->dibuat['produksi'][] = $nota->production_number;
        }
    }

    // ------------------------------------------------------------ stok opname

    /**
     * Satu opname dengan selisih kecil — pipa terpakai sedikit lebih boros dari
     * catatan. Itu justru yang sering terjadi, dan memperlihatkan gunanya menu
     * ini.
     */
    private function stokOpname(): void
    {
        $gudang = Warehouse::where('name', 'like', '%Komponen%')->value('id') ?? Warehouse::value('id');
        $tanggal = now()->subDays(2)->toDateString();

        $opname = MaterialOpname::create([
            'opname_number' => DocumentNumber::next('material_opnames', Carbon::parse($tanggal)),
            'opname_date' => $tanggal,
            'warehouse_id' => $gudang,
            'counted_by' => 'Kepala Bengkel',
            'status' => 'draft',
            'notes' => 'Hitung fisik akhir bulan.',
        ]);

        // selisih dalam satuan dasar: kurang sedikit karena potongan terbuang
        $selisih = ['PIPA-28' => -120, 'PIPA-32' => -85, 'PLAT-08' => -4200, 'GLASSWOOL' => 0];

        foreach ($selisih as $sku => $beda) {
            $bahan = Material::where('sku', $sku)->first();

            if (! $bahan) {
                continue;
            }

            $opname->items()->create([
                'material_id' => $bahan->id,
                'rack_id' => Rack::where('code', 'A1')->value('id'),
                'system_qty' => (float) $bahan->stock,
                'physical_qty' => (float) $bahan->stock + $beda,
            ]);
        }

        $this->posting->postMaterialOpname($opname->refresh());
        $this->dibuat['opname'][] = $opname->opname_number;
    }

    // ------------------------------------------------------- rencana produksi

    /**
     * Rencana bulan depan sengaja dibuat lebih besar dari stok, supaya daftar
     * belanjanya berisi — itu justru inti fiturnya.
     */
    private function rencanaProduksi(): void
    {
        $tanggal = now()->addMonth()->startOfMonth()->toDateString();

        $rencana = ProductionPlan::create([
            'plan_number' => DocumentNumber::next('production_plans', Carbon::parse($tanggal)),
            'planned_for' => $tanggal,
            'title' => 'Produksi '.now()->addMonth()->translatedFormat('F Y'),
            'notes' => 'Target menjelang musim touring akhir tahun.',
        ]);

        $target = [
            'M3-STD-RACING-V1' => 25,
            'BEAT-RACING-V1' => 20,
            'NMAX-FULLSYS-V1' => 12,
            'VARIO-BOBOK-V1' => 15,
        ];

        $i = 0;

        foreach ($target as $kode => $jumlah) {
            $formula = Formula::where('code', $kode)->first();

            if (! $formula) {
                continue;
            }

            $rencana->lines()->create([
                'formula_id' => $formula->id,
                'target_qty' => $jumlah,
                'sort_order' => ++$i,
            ]);
        }

        $this->dibuat['rencana'][] = $rencana->plan_number;
    }

    // --------------------------------------------------------------- laporan

    private function ringkasan(): void
    {
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

        $this->command?->newLine();
        $this->command?->info('Data presentasi selesai dibuat:');

        foreach ($this->dibuat as $jenis => $daftar) {
            if ($daftar === []) {
                continue;
            }

            // saldo_awal berisi baris array, bukan nomor dokumen.
            $ringkas = collect($daftar)
                ->map(fn ($x) => is_array($x) ? 'dompet #'.($x['wallet_id'] ?? '?') : (string) $x)
                ->take(6)->implode(', ');

            $this->command?->line(sprintf('  %-11s %d — %s', $jenis, count($daftar), $ringkas));
        }

        $this->command?->newLine();

        foreach (Formula::with(['materials.material', 'costs.component', 'machines.machine'])
            ->orderBy('code')->get() as $f) {
            $this->command?->line(sprintf('  %-20s HPP %-12s jual %s',
                $f->code, $rp($f->hppPerUnit()), $rp($f->item?->sell_price ?? 0)));
        }
    }

    // ------------------------------------------------------------------ data

    /**
     * Formula tambahan.
     *
     * bahan : [grup, sku, mode, [panjang, lebar, diameter], potong, waste, qty-langsung]
     * proses: [grup, slug, menit]
     * mesin : [grup, kode, menit]
     */
    private const FORMULA = [
        'BEAT-RACING-V1' => [
            'nama' => 'Honda Beat — Racing V1',
            'motor' => 'honda-beat',
            'catatan' => 'Bodi lebih pendek dari Mio, pemakaian plat lebih hemat.',
            'bahan' => [
                ['header', 'PIPA-28', 'length', [140, null, null], 1, 10, 0],
                ['header', 'PIPA-32', 'length', [200, null, null], 1, 10, 0],
                ['header', 'PIPA-CONE', 'length', [100, null, null], 1, 5, 0],
                ['silencer', 'PLAT-08', 'rect', [300, 300, null], 1, 3, 0],
                ['silencer', 'PLAT-12', 'circle', [null, null, 100], 2, 3, 0],
                ['silencer', 'CORE-30', 'direct', [null, null, null], 1, 5, 1],
                ['silencer', 'GLASSWOOL', 'direct', [null, null, null], 1, 5, 250],
                ['silencer', 'DBKILLER', 'direct', [null, null, null], 1, 5, 1],
                ['mounting', 'PLAT-50', 'rect', [75, 75, null], 1, 3, 0],
                ['mounting', 'PLAT-30', 'rect', [90, 40, null], 1, 3, 0],
                ['mounting', 'PEGAS', 'direct', [null, null, null], 1, 5, 2],
                ['mounting', 'BAUT-SET', 'direct', [null, null, null], 1, 5, 1],
                ['mounting', 'KARET-MNT', 'direct', [null, null, null], 1, 5, 2],
                ['consumable', 'KAWAT-LAS', 'direct', [null, null, null], 1, 0, 50],
                ['consumable', 'GAS-ARGON', 'direct', [null, null, null], 1, 0, 120000],
                ['consumable', 'MATA-GERINDA', 'direct', [null, null, null], 1, 0, 0.2],
                ['consumable', 'AMPLAS', 'direct', [null, null, null], 1, 0, 2],
                ['consumable', 'COMPOUND', 'direct', [null, null, null], 1, 0, 25],
            ],
            'proses' => [
                ['header', 'potong-pipa', 8], ['header', 'bending-pipa', 12],
                ['header', 'pembuatan-cone', 8], ['header', 'las-header', 12],
                ['silencer', 'potong-plat', 8], ['silencer', 'roll-body', 8],
                ['silencer', 'las-silencer', 16], ['silencer', 'pasang-packing', 8],
                ['finishing', 'gerinda', 12], ['finishing', 'poles', 16],
                ['finishing', 'assembly', 8], ['finishing', 'qc', 4],
            ],
            'mesin' => [
                ['header', 'BENDING', 12], ['header', 'LAS', 28],
                ['silencer', 'ROLL', 8], ['finishing', 'GERINDA', 12],
                ['finishing', 'POLES', 16], ['finishing', 'KOMPRESOR', 8],
            ],
        ],

        'NMAX-FULLSYS-V1' => [
            'nama' => 'Yamaha NMAX — Full System',
            'motor' => 'yamaha-nmax',
            'catatan' => 'Full system: pipa lebih panjang, silencer lebih besar, waktu las lebih lama.',
            'bahan' => [
                ['header', 'PIPA-32', 'length', [260, null, null], 1, 10, 0],
                ['header', 'PIPA-38', 'length', [320, null, null], 1, 10, 0],
                ['header', 'PIPA-CONE', 'length', [140, null, null], 1, 5, 0],
                ['silencer', 'PLAT-08', 'rect', [380, 340, null], 1, 3, 0],
                ['silencer', 'PLAT-12', 'circle', [null, null, 120], 2, 3, 0],
                ['silencer', 'CORE-30', 'direct', [null, null, null], 1, 5, 1],
                ['silencer', 'GLASSWOOL', 'direct', [null, null, null], 1, 5, 420],
                ['silencer', 'DBKILLER', 'direct', [null, null, null], 1, 5, 1],
                ['mounting', 'PLAT-50', 'rect', [90, 90, null], 2, 3, 0],
                ['mounting', 'PLAT-30', 'rect', [120, 45, null], 2, 3, 0],
                ['mounting', 'PEGAS', 'direct', [null, null, null], 1, 5, 3],
                ['mounting', 'BAUT-SET', 'direct', [null, null, null], 1, 5, 2],
                ['mounting', 'KARET-MNT', 'direct', [null, null, null], 1, 5, 3],
                ['consumable', 'KAWAT-LAS', 'direct', [null, null, null], 1, 0, 90],
                ['consumable', 'GAS-ARGON', 'direct', [null, null, null], 1, 0, 220000],
                ['consumable', 'MATA-GERINDA', 'direct', [null, null, null], 1, 0, 0.35],
                ['consumable', 'AMPLAS', 'direct', [null, null, null], 1, 0, 3],
                ['consumable', 'COMPOUND', 'direct', [null, null, null], 1, 0, 45],
            ],
            'proses' => [
                ['header', 'potong-pipa', 14], ['header', 'bending-pipa', 22],
                ['header', 'pembuatan-cone', 14], ['header', 'las-header', 22],
                ['silencer', 'potong-plat', 14], ['silencer', 'roll-body', 14],
                ['silencer', 'las-silencer', 28], ['silencer', 'pasang-packing', 12],
                ['finishing', 'gerinda', 20], ['finishing', 'poles', 26],
                ['finishing', 'assembly', 14], ['finishing', 'qc', 6],
            ],
            'mesin' => [
                ['header', 'BENDING', 22], ['header', 'LAS', 50],
                ['silencer', 'ROLL', 14], ['finishing', 'GERINDA', 20],
                ['finishing', 'POLES', 26], ['finishing', 'KOMPRESOR', 14],
            ],
        ],

        'VARIO-BOBOK-V1' => [
            'nama' => 'Honda Vario 160 — Bobokan',
            'motor' => 'honda-vario-160',
            'catatan' => 'Header standar dipakai ulang; pekerjaan terpusat di silencer.',
            'bahan' => [
                ['header', 'PIPA-28', 'length', [120, null, null], 1, 10, 0],
                ['header', 'PIPA-CONE', 'length', [110, null, null], 1, 5, 0],
                ['silencer', 'PLAT-08', 'rect', [320, 310, null], 1, 3, 0],
                ['silencer', 'PLAT-12', 'circle', [null, null, 105], 2, 3, 0],
                ['silencer', 'CORE-30', 'direct', [null, null, null], 1, 5, 1],
                ['silencer', 'GLASSWOOL', 'direct', [null, null, null], 1, 5, 300],
                ['silencer', 'DBKILLER', 'direct', [null, null, null], 1, 5, 1],
                ['mounting', 'PLAT-30', 'rect', [100, 40, null], 1, 3, 0],
                ['mounting', 'PEGAS', 'direct', [null, null, null], 1, 5, 2],
                ['mounting', 'BAUT-SET', 'direct', [null, null, null], 1, 5, 1],
                ['mounting', 'KARET-MNT', 'direct', [null, null, null], 1, 5, 2],
                ['consumable', 'KAWAT-LAS', 'direct', [null, null, null], 1, 0, 45],
                ['consumable', 'GAS-ARGON', 'direct', [null, null, null], 1, 0, 110000],
                ['consumable', 'MATA-GERINDA', 'direct', [null, null, null], 1, 0, 0.2],
                ['consumable', 'AMPLAS', 'direct', [null, null, null], 1, 0, 2],
                ['consumable', 'COMPOUND', 'direct', [null, null, null], 1, 0, 25],
            ],
            'proses' => [
                ['header', 'potong-pipa', 6], ['header', 'pembuatan-cone', 8],
                ['header', 'las-header', 10],
                ['silencer', 'potong-plat', 8], ['silencer', 'roll-body', 8],
                ['silencer', 'las-silencer', 18], ['silencer', 'pasang-packing', 8],
                ['finishing', 'gerinda', 12], ['finishing', 'poles', 14],
                ['finishing', 'assembly', 8], ['finishing', 'qc', 4],
            ],
            'mesin' => [
                ['header', 'LAS', 24], ['silencer', 'ROLL', 8],
                ['finishing', 'GERINDA', 12], ['finishing', 'POLES', 14],
                ['finishing', 'KOMPRESOR', 8],
            ],
        ],
    ];

    /** Belanja bahan: [tanggal, [[sku, jumlah satuan beli, rak], ...]] */
    private const BELANJA = [
        ['2026-08-10', [
            ['PIPA-28', 12, 'A1'], ['PIPA-32', 10, 'A1'], ['PIPA-38', 8, 'A1'],
            ['PIPA-CONE', 8, 'A1'], ['PLAT-08', 6, 'A2'], ['PLAT-12', 3, 'A2'],
        ]],
        ['2026-08-26', [
            ['PLAT-30', 2, 'A2'], ['PLAT-50', 2, 'A2'], ['CORE-30', 60, 'A3'],
            ['GLASSWOOL', 25, 'A3'], ['DBKILLER', 60, 'A3'],
            ['PEGAS', 150, 'A4'], ['BAUT-SET', 60, 'A4'], ['KARET-MNT', 150, 'A4'],
        ]],
        ['2026-09-02', [
            ['KAWAT-LAS', 8, 'A3'], ['GAS-ARGON', 3, 'A3'], ['MATA-GERINDA', 40, 'A3'],
            ['AMPLAS', 120, 'A3'], ['COMPOUND', 3, 'A3'],
            ['PIPA-28', 6, 'A1'], ['PLAT-08', 4, 'A2'],
        ]],
    ];

    /** Nota produksi: [tanggal, kode formula, jumlah resep] */
    private const PRODUKSI = [
        ['2026-08-18', 'M3-STD-RACING-V1', 6],
        ['2026-08-29', 'BEAT-RACING-V1', 8],
        ['2026-09-01', 'M3-STD-RACING-V1', 5],
        ['2026-09-03', 'NMAX-FULLSYS-V1', 4],
        ['2026-09-04', 'VARIO-BOBOK-V1', 6],
    ];
}
