<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialOpname;
use App\Models\MaterialPurchase;
use App\Models\MaterialStock;
use App\Models\OverheadItem;
use App\Models\Production;
use App\Models\StockMovement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembukuan modul produksi.
 *
 * Sengaja terpisah dari PostingService (modul barang jual) supaya modul yang
 * sudah berjalan tidak tersentuh sama sekali. Polanya sama: hanya kelas ini
 * yang boleh menulis material_movements, dan nota `draft` tidak menyentuh apa
 * pun sampai dibukukan.
 *
 * Harga pokok bahan memakai biaya pembelian terakhir. Saat produksi dibukukan,
 * nilainya dibekukan ke baris nota agar HPP historis tidak ikut berubah ketika
 * harga pipa naik bulan depan.
 */
class ProductionPostingService
{
    // ------------------------------------------------- pembelian bahan baku

    public function postPurchase(MaterialPurchase $purchase): void
    {
        if ($purchase->isPosted()) {
            throw new RuntimeException('Nota pembelian bahan ini sudah dibukukan.');
        }

        $purchase->loadMissing('items.material');

        if ($purchase->items->isEmpty()) {
            throw new RuntimeException('Tidak bisa membukukan pembelian tanpa baris bahan.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->recalculateTotals();

            foreach ($purchase->items as $line) {
                // Harga beli terakhir jadi harga pokok berjalan. Diperbarui
                // lebih dulu supaya basePrice() di bawah memakai harga baru.
                $line->material->forceFill(['cost_price' => $line->unit_cost])->save();
                $line->material->refresh();

                // Nota dicatat dalam satuan beli (7 batang), stok disimpan dalam
                // satuan dasar (42.000 mm) supaya sisa potongan bisa dinyatakan.
                $this->addStock(
                    material: $line->material,
                    rackId: $line->rack_id,
                    qty: $line->material->toBase((float) $line->qty),
                    unitCost: $line->material->basePrice(),
                    type: 'purchase',
                    source: $purchase,
                    movedAt: $purchase->purchased_at,
                    notes: 'Pembelian bahan '.$purchase->invoice_number
                        .' ('.rtrim(rtrim((string) $line->qty, '0'), '.').' '.$line->material->unit.')',
                );
            }

            $this->moveWallet(
                wallet: $purchase->wallet,
                direction: 'out',
                amount: (float) $purchase->total,
                source: $purchase,
                occurredAt: $purchase->purchased_at,
                description: 'Pembelian bahan '.$purchase->invoice_number,
            );

            $purchase->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    public function unpostPurchase(MaterialPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $this->reverse($purchase);
            $purchase->forceFill(['status' => 'draft', 'posted_at' => null])->save();
        });
    }

    // ------------------------------------------------------------- produksi

    /**
     * Salin kebutuhan dari formula ke nota produksi.
     * Dipanggil sebelum pembukuan agar isinya masih bisa disesuaikan bila
     * pemakaian nyata berbeda dari resep.
     */
    public function fillFromFormula(Production $production): void
    {
        $production->loadMissing([
            'formula.materials.material', 'formula.costs.component', 'formula.machines.machine',
        ]);
        $formula = $production->formula;

        if (! $formula) {
            throw new RuntimeException('Nota produksi belum memilih formula.');
        }

        $batch = (float) $production->batch_qty;

        DB::transaction(function () use ($production, $formula, $batch) {
            $production->materials()->delete();
            $production->costs()->delete();
            $production->machines()->delete();

            foreach ($formula->materials as $line) {
                $production->materials()->create([
                    'material_id' => $line->material_id,
                    'qty' => $line->effectiveQty() * $batch,
                    // qty formula memakai satuan dasar, jadi harganya pun harus
                    // per satuan dasar — bukan per satuan beli.
                    'unit_cost' => $line->material?->basePrice() ?? 0,
                ]);
            }

            foreach ($formula->costs as $line) {
                $hourly = $line->component?->isHourly() ?? false;

                $production->costs()->create([
                    'cost_component_id' => $line->cost_component_id,
                    // Proses per jam dikali batch lewat menitnya, bukan lewat
                    // qty — supaya angka menit di nota masih terbaca wajar.
                    'qty' => $hourly ? 1 : (float) $line->qty * $batch,
                    'minutes' => $hourly ? (float) $line->minutes * $batch : 0,
                    'rate_type' => $hourly ? 'per_hour' : 'per_unit',
                    'rate' => (float) ($line->component->rate ?? 0),
                ]);
            }

            foreach ($formula->machines as $line) {
                $production->machines()->create([
                    'machine_id' => $line->machine_id,
                    'minutes' => (float) $line->minutes * $batch,
                    'rate' => $line->machine?->hourlyCost() ?? 0,
                ]);
            }

            $production->forceFill([
                'output_qty' => (float) $formula->output_qty * $batch,
            ])->save();
        });
    }

    public function postProduction(Production $production): void
    {
        if ($production->isPosted()) {
            throw new RuntimeException('Nota produksi ini sudah dibukukan.');
        }

        $production->loadMissing([
            'formula', 'materials.material', 'costs.component', 'machines.machine',
        ]);

        if ($production->materials->isEmpty()) {
            throw new RuntimeException('Belum ada baris bahan. Tekan "Ambil dari formula" dulu.');
        }

        // Cek kecukupan stok lebih dulu supaya nota tidak setengah terbukukan.
        foreach ($production->materials as $line) {
            $tersedia = (float) ($line->material->stock ?? 0);

            if ($tersedia < (float) $line->qty) {
                throw new RuntimeException(sprintf(
                    'Stok %s tidak cukup: tersedia %s, dibutuhkan %s.',
                    $line->material->name,
                    $line->material->formatBase($tersedia),
                    $line->material->formatBase((float) $line->qty),
                ));
            }
        }

        DB::transaction(function () use ($production) {
            $biayaBahan = 0.0;
            $biayaJasa = 0.0;
            $biayaMesin = 0.0;
            $totalMenit = 0.0;

            foreach ($production->materials as $line) {
                $unitCost = $line->material?->basePrice() ?? 0;
                $line->forceFill(['unit_cost' => $unitCost])->save();
                $biayaBahan += $unitCost * (float) $line->qty;

                $this->consumeStock(
                    material: $line->material,
                    qty: (float) $line->qty,
                    unitCost: $unitCost,
                    source: $production,
                    movedAt: $production->produced_at,
                    notes: 'Produksi '.$production->production_number,
                );
            }

            foreach ($production->costs as $line) {
                // Tarif dan tipenya dibekukan di sini, lalu subtotal-nya
                // dihitung sekali saja oleh model — rumus yang ditulis dua
                // kali pernah membuat upah per jam terhitung sebagai borongan.
                $line->forceFill([
                    'rate' => (float) ($line->component->rate ?? 0),
                    'rate_type' => $line->component?->rate_type ?? 'per_unit',
                ])->save();

                $biayaJasa += (float) $line->subtotal;
                $totalMenit += (float) $line->minutes;
            }

            foreach ($production->machines as $line) {
                $line->forceFill(['rate' => $line->machine?->hourlyCost() ?? 0])->save();
                $biayaMesin += (float) $line->subtotal;
            }

            // Overhead dibekukan sebagai rupiah: kalau sewa naik bulan depan,
            // HPP nota yang sudah dibukukan tidak ikut berubah.
            $overhead = OverheadItem::perUnit() * (float) $production->output_qty;
            $total = $biayaBahan + $biayaJasa + $biayaMesin + $overhead;
            $output = (float) $production->output_qty;

            $hpp = $output > 0 ? $total / $output : 0;

            $production->forceFill([
                'material_cost' => $biayaBahan,
                'service_cost' => $biayaJasa,
                'machine_cost' => $biayaMesin,
                'total_minutes' => $totalMenit,
                'overhead_cost' => $overhead,
                'total_cost' => $total,
                'hpp_per_unit' => $hpp,
                'status' => 'posted',
                'posted_at' => now(),
            ])->save();

            // Barang jadi masuk ke inventory barang jual, kalau formulanya
            // memang menunjuk satu. Inilah yang menyambung produksi ke
            // penjualan: tanpa ini HPP terhitung tapi barangnya tidak pernah
            // bisa dijual.
            $this->addFinishedGoods($production, $output, $hpp);
        });
    }

    /**
     * Masukkan barang jadi ke inventory barang jual.
     *
     * Harga pokok barang mengikuti HPP produksi terakhir, sehingga laba di menu
     * Penjualan dihitung dari modal produksi sebenarnya, bukan angka tebakan.
     */
    private function addFinishedGoods(Production $production, float $qty, float $hpp): void
    {
        $item = $production->formula?->item;

        if (! $item || $qty <= 0) {
            return;
        }

        $balance = (float) $item->stock + $qty;

        StockMovement::create([
            'item_id' => $item->id,
            'type' => 'production',
            'qty_in' => $qty,
            'qty_out' => 0,
            'balance_after' => $balance,
            'unit_cost' => $hpp,
            'source_type' => $production::class,
            'source_id' => $production->id,
            'moved_at' => $production->produced_at,
            'notes' => 'Hasil produksi '.$production->production_number,
        ]);

        $item->forceFill(['stock' => $balance, 'cost_price' => $hpp])->save();
    }

    public function unpostProduction(Production $production): void
    {
        // Kalau barang jadinya sudah terlanjur dijual, membatalkan produksi
        // akan membuat stok minus dan laba penjualan lama jadi omong kosong.
        // Lebih baik ditolak daripada diam-diam merusak angka.
        $item = $production->loadMissing('formula.item')->formula?->item;

        if ($item && $production->isPosted()) {
            $sisaSetelahDibatalkan = (float) $item->stock - (float) $production->output_qty;

            if ($sisaSetelahDibatalkan < 0) {
                throw new RuntimeException(sprintf(
                    'Tidak bisa dibatalkan: %s tersisa %s, sedangkan nota ini menyumbang %s. Sebagian sudah terjual — batalkan nota penjualannya dulu.',
                    $item->name,
                    rtrim(rtrim((string) $item->stock, '0'), '.'),
                    rtrim(rtrim((string) $production->output_qty, '0'), '.'),
                ));
            }
        }

        DB::transaction(function () use ($production) {
            $this->reverse($production);
            $production->forceFill([
                'status' => 'draft',
                'posted_at' => null,
                'material_cost' => 0,
                'service_cost' => 0,
                'machine_cost' => 0,
                'total_minutes' => 0,
                'overhead_cost' => 0,
                'total_cost' => 0,
                'hpp_per_unit' => 0,
            ])->save();
        });
    }

    // -------------------------------------------------- stok opname bahan

    /**
     * Bukukan hasil hitung fisik bahan.
     *
     * Hanya baris yang selisih yang mengoreksi stok, dan koreksinya tetap lewat
     * kartu mutasi — jadi setiap penyesuaian punya jejak, bukan angka yang
     * tiba-tiba berubah tanpa alasan.
     */
    public function postMaterialOpname(MaterialOpname $opname): void
    {
        if ($opname->isPosted()) {
            throw new RuntimeException('Stok opname bahan ini sudah dibukukan.');
        }

        $opname->loadMissing('items.material');

        DB::transaction(function () use ($opname) {
            foreach ($opname->items as $line) {
                $selisih = (float) $line->difference;

                if (abs($selisih) < 0.0001) {
                    continue; // cocok, tidak perlu koreksi
                }

                $catatan = 'Stok opname bahan '.$opname->opname_number;
                $harga = $line->material?->basePrice() ?? 0;

                if ($selisih > 0) {
                    $this->addStock(
                        material: $line->material,
                        rackId: $line->rack_id,
                        qty: $selisih,
                        unitCost: $harga,
                        type: 'opname',
                        source: $opname,
                        movedAt: $opname->opname_date,
                        notes: $catatan,
                    );

                    continue;
                }

                // Selisih kurang: keluarkan dari rak yang dihitung.
                $keluar = abs($selisih);
                $material = $line->material;
                $balance = (float) $material->stock - $keluar;

                MaterialMovement::create([
                    'material_id' => $material->id,
                    'rack_id' => $line->rack_id,
                    'type' => 'opname',
                    'qty_in' => 0,
                    'qty_out' => $keluar,
                    'balance_after' => $balance,
                    'unit_cost' => $harga,
                    'source_type' => $opname::class,
                    'source_id' => $opname->id,
                    'moved_at' => $opname->opname_date,
                    'notes' => $catatan,
                ]);

                $material->forceFill(['stock' => $balance])->save();

                if ($line->rack_id) {
                    $this->adjustRackStock($material->id, $line->rack_id, -$keluar);
                }
            }

            $opname->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        });
    }

    public function unpostMaterialOpname(MaterialOpname $opname): void
    {
        DB::transaction(function () use ($opname) {
            $this->reverse($opname);
            $opname->forceFill(['status' => 'draft', 'posted_at' => null])->save();
        });
    }

    // -------------------------------------------------------------- helpers

    private function addStock(Material $material, ?int $rackId, float $qty, float $unitCost, string $type, $source, $movedAt, ?string $notes = null): void
    {
        $balance = (float) $material->stock + $qty;

        MaterialMovement::create([
            'material_id' => $material->id,
            'rack_id' => $rackId,
            'type' => $type,
            'qty_in' => $qty,
            'qty_out' => 0,
            'balance_after' => $balance,
            'unit_cost' => $unitCost,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'moved_at' => $movedAt,
            'notes' => $notes,
        ]);

        $material->forceFill(['stock' => $balance])->save();

        if ($rackId) {
            $this->adjustRackStock($material->id, $rackId, $qty);
        }
    }

    /**
     * Ambil bahan dari rak yang punya stok, rak dengan isi terbanyak dulu.
     * Kalau tersebar di beberapa rak, pemakaian dipecah otomatis.
     */
    private function consumeStock(Material $material, float $qty, float $unitCost, $source, $movedAt, ?string $notes = null): void
    {
        $sisa = $qty;

        $stocks = MaterialStock::where('material_id', $material->id)
            ->where('qty', '>', 0)
            ->orderByDesc('qty')
            ->get();

        foreach ($stocks as $stock) {
            if ($sisa <= 0) {
                break;
            }

            $ambil = min($sisa, (float) $stock->qty);
            $sisa -= $ambil;

            $material->refresh();
            $balance = (float) $material->stock - $ambil;

            MaterialMovement::create([
                'material_id' => $material->id,
                'rack_id' => $stock->rack_id,
                'type' => 'production',
                'qty_in' => 0,
                'qty_out' => $ambil,
                'balance_after' => $balance,
                'unit_cost' => $unitCost,
                'source_type' => $source::class,
                'source_id' => $source->id,
                'moved_at' => $movedAt,
                'notes' => $notes,
            ]);

            $material->forceFill(['stock' => $balance])->save();
            $this->adjustRackStock($material->id, $stock->rack_id, -$ambil);
        }

        // Bahan yang stoknya belum pernah ditempatkan di rak mana pun tetap
        // harus tercatat keluar, agar total stok tidak melenceng.
        if ($sisa > 0) {
            $material->refresh();
            $balance = (float) $material->stock - $sisa;

            MaterialMovement::create([
                'material_id' => $material->id,
                'rack_id' => null,
                'type' => 'production',
                'qty_in' => 0,
                'qty_out' => $sisa,
                'balance_after' => $balance,
                'unit_cost' => $unitCost,
                'source_type' => $source::class,
                'source_id' => $source->id,
                'moved_at' => $movedAt,
                'notes' => $notes.' (tanpa rak)',
            ]);

            $material->forceFill(['stock' => $balance])->save();
        }
    }

    private function adjustRackStock(int $materialId, int $rackId, float $delta): void
    {
        $stock = MaterialStock::firstOrCreate(
            ['material_id' => $materialId, 'rack_id' => $rackId],
            ['qty' => 0]
        );

        $stock->forceFill(['qty' => (float) $stock->qty + $delta])->save();
    }

    private function moveWallet(?Wallet $wallet, string $direction, float $amount, $source, $occurredAt, ?string $description = null): void
    {
        if (! $wallet || abs($amount) < 0.0001) {
            return;
        }

        $balance = $direction === 'in'
            ? (float) $wallet->current_balance + $amount
            : (float) $wallet->current_balance - $amount;

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'occurred_at' => $occurredAt,
            'description' => $description,
        ]);

        $wallet->forceFill(['current_balance' => $balance])->save();
    }

    /**
     * Hapus jejak sebuah nota lalu hitung ulang. Menghitung ulang lebih aman
     * daripada menulis mutasi kebalikan, karena hasilnya tetap benar walau ada
     * nota lain yang dibukukan sesudahnya.
     */
    private function reverse($document): void
    {
        $materialIds = MaterialMovement::where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('material_id')->unique();

        $walletIds = WalletTransaction::where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('wallet_id')->unique();

        // Kembalikan dulu isi rak sebelum barisnya dihapus.
        foreach (MaterialMovement::where('source_type', $document::class)->where('source_id', $document->id)->get() as $m) {
            if ($m->rack_id) {
                $this->adjustRackStock($m->material_id, $m->rack_id, (float) $m->qty_out - (float) $m->qty_in);
            }
        }

        // Barang jadi yang sempat masuk inventory barang jual ikut ditarik.
        $itemIds = StockMovement::where('source_type', $document::class)
            ->where('source_id', $document->id)
            ->pluck('item_id')->unique();

        MaterialMovement::where('source_type', $document::class)->where('source_id', $document->id)->delete();
        WalletTransaction::where('source_type', $document::class)->where('source_id', $document->id)->delete();
        StockMovement::where('source_type', $document::class)->where('source_id', $document->id)->delete();

        Material::whereIn('id', $materialIds)->get()->each->recalculateStock();
        Wallet::whereIn('id', $walletIds)->get()->each->recalculateBalance();
        Item::whereIn('id', $itemIds)->get()->each->recalculateStock();
    }
}
