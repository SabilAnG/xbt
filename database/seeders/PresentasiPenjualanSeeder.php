<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\PriceTier;
use App\Models\Sale;
use App\Models\Wallet;
use App\Services\DocumentNumber;
use App\Services\PostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Penjualan contoh untuk presentasi — penutup rantai produksi.
 *
 * Barang yang dijual di sini adalah barang jadi hasil nota produksi, dengan
 * harga yang benar-benar diambil dari master Tingkatan Harga. Jadi laba yang
 * muncul di laporan bukan angka karangan: omzet dikurangi HPP yang dibekukan
 * saat produksi dibukukan.
 *
 * Sengaja memakai empat tingkatan berbeda supaya terlihat bedanya menjual ke
 * reseller, toko, pembeli langsung, dan marketplace. Potongan marketplace
 * dicatat sebagai diskon nota, karena memang itu uang yang tidak diterima.
 *
 * Jalankan SETELAH PresentasiProduksiSeeder — barangnya harus ada dulu.
 */
class PresentasiPenjualanSeeder extends Seeder
{
    public const MANIFEST = 'presentasi-penjualan.json';

    /** @var array<int, string> */
    private array $dibuat = [];

    public function run(): void
    {
        if (Storage::disk('local')->exists(self::MANIFEST)) {
            $this->command?->warn(
                'Penjualan presentasi sudah pernah dibuat. Cabut dulu dengan '
                .'deploy/hapus-data-presentasi.php bila ingin menyusun ulang.'
            );

            return;
        }

        if (Item::where('stock', '>', 0)->count() === 0) {
            $this->command?->error(
                'Belum ada barang jadi di stok. Jalankan PresentasiProduksiSeeder dulu.'
            );

            return;
        }

        $posting = app(PostingService::class);
        $tunai = Wallet::where('type', 'cash')->value('id');
        $bank = Wallet::where('type', 'bank')->value('id');

        foreach (self::PENJUALAN as [$tanggal, $pembeli, $telepon, $tingkat, $bayar, $baris, $catatan]) {
            $tier = PriceTier::where('slug', $tingkat)->first();

            if (! $tier) {
                continue;
            }

            $nota = Sale::create([
                'invoice_number' => DocumentNumber::next('sales', Carbon::parse($tanggal)),
                'sold_at' => $tanggal,
                'customer_name' => $pembeli,
                'customer_phone' => $telepon,
                'wallet_id' => $bayar === 'tunai' ? $tunai : $bank,
                'status' => 'draft',
                'notes' => $catatan ?: null,
            ]);

            $kotor = 0.0;

            foreach ($baris as [$sku, $qty]) {
                $item = Item::where('sku', $sku)->first();

                if (! $item) {
                    continue;
                }

                // Harga diturunkan dari HPP lewat tingkatan harga, bukan diketik.
                $harga = $tier->price((float) $item->cost_price);
                $kotor += $harga * $qty;

                $nota->items()->create([
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price' => $harga,
                    'unit_cost' => (float) $item->cost_price,
                ]);
            }

            // Potongan marketplace: uang yang dipotong platform sebelum masuk
            // rekening. Dicatat sebagai diskon agar arus kas jujur.
            if ((float) $tier->fee_percent > 0) {
                $nota->forceFill([
                    'discount' => round($kotor * ((float) $tier->fee_percent / 100)),
                ])->save();
            }

            $nota->recalculateTotals();
            $posting->postSale($nota->refresh());

            $this->dibuat[] = $nota->invoice_number;
        }

        Storage::disk('local')->put(self::MANIFEST, json_encode([
            'dibuat_pada' => now()->toDateTimeString(),
            'penjualan' => $this->dibuat,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->ringkasan();
    }

    private function ringkasan(): void
    {
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');

        $omzet = (float) Sale::posted()->sum('total');
        $modal = (float) Sale::posted()->sum('total_cost');

        $this->command?->newLine();
        $this->command?->info(count($this->dibuat).' nota penjualan dibuat.');
        $this->command?->line('  omzet       '.$rp($omzet));
        $this->command?->line('  modal (HPP) '.$rp($modal));
        $this->command?->line('  laba kotor  '.$rp($omzet - $modal)
            .($omzet > 0 ? sprintf('  (%.1f%% dari omzet)', ($omzet - $modal) / $omzet * 100) : ''));

        $this->command?->newLine();

        foreach (Item::orderBy('sku')->get() as $i) {
            $this->command?->line(sprintf('  %-30s sisa %s set',
                $i->name, rtrim(rtrim((string) $i->stock, '0'), '.')));
        }
    }

    /**
     * [tanggal, pembeli, telepon, slug tingkatan, dompet, [[sku, qty], ...], catatan]
     *
     * Urutannya kronologis dan jumlahnya tidak pernah melebihi yang sudah
     * diproduksi sampai tanggal itu.
     */
    private const PENJUALAN = [
        ['2026-08-20', 'Andi Saputra', '081234567801', 'retail-normal', 'tunai',
            [['KNP-MIO-STD', 1]], 'Pasang di tempat.'],

        ['2026-08-22', 'Toko Variasi Berkah', '081234567802', 'toko-grosir', 'bank',
            [['KNP-MIO-STD', 2]], 'Langganan, transfer BCA.'],

        ['2026-08-30', 'Rizky Pratama', '081234567803', 'retail-normal', 'tunai',
            [['KNP-BEAT-RC', 1]], ''],

        ['2026-08-31', 'Shopee — Order SP-28914', '-', 'marketplace', 'bank',
            [['KNP-BEAT-RC', 2]], 'Potongan admin 12% sudah dikurangkan.'],

        ['2026-09-02', 'Bengkel Jaya Motor', '081234567804', 'reseller', 'bank',
            [['KNP-BEAT-RC', 3]], 'Ambil rutin tiap bulan.'],

        ['2026-09-03', 'Dimas Kurniawan', '081234567805', 'retail-normal', 'tunai',
            [['KNP-MIO-STD', 1]], ''],

        ['2026-09-04', 'Toko Variasi Berkah', '081234567802', 'toko-grosir', 'bank',
            [['KNP-MIO-STD', 2], ['KNP-NMAX-FS', 1]], ''],

        ['2026-09-05', 'Hendra Wijaya', '081234567806', 'retail-normal', 'tunai',
            [['KNP-VARIO-BB', 1], ['KNP-NMAX-FS', 1]], 'Bayar tunai di bengkel.'],

        ['2026-09-06', 'Tokopedia — Order TP-44170', '-', 'marketplace', 'bank',
            [['KNP-MIO-STD', 1], ['KNP-VARIO-BB', 2]], 'Potongan admin 12% sudah dikurangkan.'],
    ];
}
