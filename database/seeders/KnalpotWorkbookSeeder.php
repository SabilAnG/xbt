<?php

namespace Database\Seeders;

use App\Models\AttendanceDay;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseType;
use App\Models\Formula;
use App\Models\FormulaMaterial;
use App\Models\Host;
use App\Models\HostPayroll;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ListOption;
use App\Models\LiveSession;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\ProcurementHistory;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Services\DocumentNumber;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Impor buku kerja "Manajemen Produksi & Penjualan Knalpot.xlsx".
 *
 * Datanya disalin ke database/data/knalpot-workbook.json. Seeder ini mengisi
 * master (produk, bahan, resep) lalu memutar ulang transaksi lewat
 * InventoryService dalam urutan tanggal, sehingga stok di admin sama dengan
 * kolom SISA STOK di buku kerja.
 *
 * Transaksi hanya diimpor bila tabelnya masih kosong, jadi menjalankan ulang
 * seeder tidak membuat data dobel.
 */
class KnalpotWorkbookSeeder extends Seeder
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<string, Material> */
    private array $materials = [];

    public function run(InventoryService $inventory): void
    {
        $path = database_path('data/knalpot-workbook.json');
        $this->data = json_decode((string) file_get_contents($path), true) ?? throw new RuntimeException("Tidak bisa membaca {$path}");

        DB::transaction(function () use ($inventory) {
            $this->listOptions();
            $this->materials();
            $this->recipes();
            $this->products();
            $this->transactions($inventory);
            $this->expenses();
            $this->hostsAndWorkers();
            $this->procurementHistory();
        });
    }

    /** Sheet Daftar: pilihan dropdown. */
    private function listOptions(): void
    {
        $map = ['model_motor' => 'model_motor', 'finishing' => 'finishing', 'tipe' => 'tipe', 'ukuran' => 'ukuran', 'merek' => 'merek', 'saluran' => 'saluran'];

        foreach ($map as $list => $key) {
            foreach ($this->data['daftar'][$key] as $order => $value) {
                ListOption::firstOrCreate(['list' => $list, 'value' => $value], ['sort_order' => $order]);
            }
        }
    }

    /** Data host & tukang (Daftar), Jam Live, Gaji Host, Absen Tukang, Gaji Tukang. */
    private function hostsAndWorkers(): void
    {
        foreach ($this->data['host'] as $row) {
            Host::firstOrCreate(['name' => $row['nama']], ['hourly_rate' => $row['tarif'], 'account' => $row['rekening']]);
        }

        foreach ($this->data['tukang'] as $order => $row) {
            Worker::firstOrCreate(['name' => $row['nama']], ['daily_wage' => $row['upah'], 'account' => $row['rekening'], 'sort_order' => $order]);
        }

        $hosts = Host::pluck('id', 'name');
        $workers = Worker::pluck('id', 'name');

        if (! LiveSession::exists()) {
            foreach ($this->data['jam_live'] as $row) {
                LiveSession::create([
                    'date' => $row['tanggal'],
                    'host_id' => $hosts[$row['host']],
                    'start_time' => $row['mulai'],
                    'end_time' => $row['selesai'],
                    'manual_hours' => $row['jam_manual'],
                    'store_account' => $row['akun'],
                    'notes' => $row['catatan'],
                ]);
            }
        }

        if (! AttendanceDay::exists()) {
            foreach ($this->data['absen_tukang'] as $row) {
                $day = AttendanceDay::create(['date' => $row['tanggal'], 'notes' => $row['catatan']]);

                foreach ($row['hadir'] as $name => $value) {
                    $day->attendances()->create(['worker_id' => $workers[$name], 'value' => $value]);
                }
            }
        }

        if (! HostPayroll::exists()) {
            foreach ($this->data['gaji_host'] as $row) {
                HostPayroll::create([
                    'period_start' => $row['mulai'], 'period_end' => $row['selesai'], 'host_id' => $hosts[$row['host']],
                    'status' => $row['status'] ?? 'Belum', 'paid_at' => $row['tgl_transfer'], 'notes' => $row['catatan'],
                ]);
            }
        }

        if (! WorkerPayroll::exists()) {
            foreach ($this->data['gaji_tukang'] as $row) {
                WorkerPayroll::create([
                    'period_start' => $row['mulai'], 'period_end' => $row['selesai'], 'worker_id' => $workers[$row['tukang']],
                    'bonus' => $row['tambahan'], 'deduction' => $row['potongan'],
                    'status' => $row['status'] ?? 'Belum', 'paid_at' => $row['tgl_bayar'], 'notes' => $row['catatan'],
                ]);
            }
        }
    }

    /** Arsip Riwayat Pengadaan. */
    private function procurementHistory(): void
    {
        if (ProcurementHistory::exists()) {
            return;
        }

        foreach ($this->data['riwayat_pengadaan'] as $row) {
            ProcurementHistory::create([
                'number' => $row['no'], 'date' => $row['tanggal'], 'period' => $row['periode'], 'name' => $row['nama'],
                'kind' => $row['jenis'], 'category' => $row['kategori'], 'form' => $row['bentuk'], 'diameter' => $row['diameter'],
                'thickness' => $row['tebal'], 'grade' => $row['grade'], 'qty' => $row['qty'], 'unit' => $row['satuan'],
                'unit_price' => $row['harga'], 'total' => $row['total'], 'description' => $row['keterangan'],
            ]);
        }
    }

    private function materials(): void
    {
        foreach ($this->data['bahan'] as $i => $row) {
            $category = MaterialCategory::firstOrCreate(
                ['slug' => Str::slug($row['kategori'])],
                ['name' => $row['kategori'], 'sort_order' => $i],
            );

            $this->materials[$row['nama']] = Material::updateOrCreate(
                ['sku' => sprintf('BHN-%03d', $row['no'])],
                [
                    'name' => $row['nama'],
                    'material_category_id' => $category->id,
                    'dimension_type' => 'count',
                    'unit' => $row['satuan'],
                    'opening_stock' => $row['stok_awal'],
                    'min_stock' => $row['stok_minimum'],
                ],
            );
        }
    }

    private function recipes(): void
    {
        foreach (collect($this->data['resep'])->groupBy('resep') as $name => $lines) {
            $formula = Formula::updateOrCreate(
                ['code' => Str::upper(Str::slug($name))],
                ['name' => $name, 'output_qty' => 1, 'output_unit' => 'pcs'],
            );

            foreach ($lines->values() as $order => $line) {
                FormulaMaterial::updateOrCreate(
                    ['formula_id' => $formula->id, 'material_id' => $this->materials[$line['bahan']]->id],
                    ['brand' => $line['merek'], 'input_mode' => 'direct', 'qty' => $line['qty'], 'sort_order' => $order],
                );
            }
        }
    }

    private function products(): void
    {
        $category = ItemCategory::firstOrCreate(['slug' => 'knalpot-jadi'], ['name' => 'Knalpot Jadi']);
        $formulas = Formula::pluck('id', 'name');

        foreach ($this->data['produk'] as $row) {
            $this->items[$row['nama']] = Item::updateOrCreate(
                ['sku' => sprintf('KNL-%03d', $row['no'])],
                [
                    'name' => $row['nama'],
                    'model_motor' => $row['model_motor'],
                    'finishing' => $row['finishing'],
                    'variant' => $row['tipe'],
                    'size' => $row['ukuran'],
                    'brand' => $row['merek'],
                    'item_category_id' => $category->id,
                    'formula_id' => $formulas[$row['resep']] ?? null,
                    'unit' => 'pcs',
                    'cost_price' => $row['hpp'],
                    'opening_stock' => $row['stok_awal'],
                    'sell_price' => $row['harga_offline'],
                    'sell_price_online' => $row['harga_online'],
                    'min_stock' => $row['stok_minimum'],
                ],
            );
        }
    }

    /**
     * Pembelian dulu, lalu produksi, penyesuaian, dan penjualan — urutan yang
     * sama dengan cara buku kerja menghitung sisa stok.
     */
    private function transactions(InventoryService $inventory): void
    {
        if (DB::table('material_purchases')->exists() || DB::table('productions')->exists() || DB::table('sales')->exists()) {
            return;
        }

        foreach ($this->data['pembelian_bahan'] as $row) {
            $inventory->recordMaterialPurchase(
                $this->materials[$row['bahan']],
                $row['qty'],
                $row['harga'],
                $row['tanggal'],
                [
                    'supplier' => $row['supplier'],
                    'recorded_in_expenses' => $row['sudah_di_pengeluaran'],
                    'paid_from' => $row['dibayar_dari'],
                    'notes' => $row['catatan'],
                ],
            );
        }

        foreach ($this->data['produksi'] as $row) {
            $inventory->recordProduction($this->items[$row['produk']], $row['qty'], $row['tanggal'], [
                'worker' => $row['tukang'],
                'notes' => $row['catatan'],
            ]);
        }

        foreach ($this->data['penyesuaian_bahan'] as $row) {
            $inventory->adjustMaterial($this->materials[$row['bahan']]->refresh(), $row['qty'], $row['tanggal'], $row['alasan']);
        }

        foreach ($this->data['penjualan'] as $row) {
            $inventory->recordSale($this->items[$row['produk']]->refresh(), $row['qty'], $row['harga'], $row['tanggal'], [
                'channel' => $row['saluran'],
                'customer_name' => $row['pembeli'],
                'paid_amount' => $row['uang_masuk'],
                'notes' => $row['keterangan'],
            ]);
        }
    }

    private function expenses(): void
    {
        $type = ExpenseType::firstOrCreate(['slug' => 'usaha-knalpot'], ['name' => 'Usaha Knalpot']);

        foreach ($this->data['daftar']['kategori_pengeluaran'] as $name) {
            ExpenseCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'expense_type_id' => $type->id],
            );
        }

        if (Expense::exists()) {
            return;
        }

        $categories = ExpenseCategory::pluck('id', 'name');

        foreach ($this->data['pengeluaran'] as $row) {
            Expense::create([
                'reference_number' => DocumentNumber::next('expenses', new \DateTimeImmutable($row['tanggal'])),
                'spent_at' => $row['tanggal'],
                'expense_category_id' => $categories[$row['kategori']],
                'amount' => $row['nominal'],
                'description' => $row['keterangan'],
                'paid_to' => $row['ke_rekening'],
                'from_account' => $row['dari_rekening'],
                'notes' => $row['catatan'],
                'data_status' => $row['status_data'],
                'status' => 'posted',
                'posted_at' => now(),
            ]);
        }
    }
}
