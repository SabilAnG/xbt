<?php

namespace App\Services;

use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\MaterialPurchase;
use App\Models\PipePiece;
use App\Models\Production;
use App\Models\ProductionCut;
use App\Models\ProductionReceipt;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membukukan dan membatalkan nota produksi.
 *
 * Saat dibukukan: bahan per satuan keluar dari gudang asalnya, pipa dipotong
 * dari potongan yang paling pas (sisa didahulukan), sisa potongan masuk
 * Gudang Stok Sisa Produksi atau diafkir bila sudah terlalu pendek, lalu
 * hasilnya masuk gudang (knalpot → Finish Good, setengah jadi → Setengah
 * Jadi). Produksi vendor: bahan dikirim, hasilnya diterima bertahap. Lalu
 * knalpot jadi masuk Finish Good dengan HPP sebenarnya.
 */
class ProductionService
{
    public function __construct(
        private ProductionPlanner $planner,
        private StockService $stock,
        private InventoryService $inventory,
    ) {}

    /**
     * Pratinjau kebutuhan & rencana potong, tanpa menyentuh stok.
     *
     * @return array{units: Collection<int, array<string, mixed>>, pipes: Collection<int, array<string, mixed>>, ready: bool, problems: list<string>, material_cost: float, waste_cost: float, other_cost: float, total_cost: float, hpp: float}
     */
    /**
     * @param  array<int|string, string>  $leftovers  pilihan sisa per potongan pipa: stok | sampah
     */
    public function preview(Production $production, array $leftovers = []): array
    {
        $production->loadMissing('formula');
        $qty = (float) $production->qty;
        $requirements = $production->formula->requirements($qty);
        $problems = [];

        $units = $requirements->reject(fn (array $line) => $line['material']->isPipe())
            ->groupBy(fn (array $line) => $line['material']->id)
            ->map(function (Collection $group) use (&$problems) {
                $material = $group->first()['material'];
                $material->loadMissing('stocks', 'warehouse');
                $need = (float) $group->sum('qty');
                $available = (float) $material->stock;

                if ($need > $available + 0.0001) {
                    $problems[] = sprintf('%s kurang %s %s (butuh %s, ada %s).', $material->label(), qty($need - $available), $material->stockUnit(), qty($need), qty($available));
                }

                return [
                    'material' => $material,
                    'lines' => $group->values(),
                    'need' => $need,
                    'available' => $available,
                    'cost' => $need * (float) $material->avg_cost,
                ];
            })->values();

        $pipes = $requirements->filter(fn (array $line) => $line['material']->isPipe())
            ->groupBy(fn (array $line) => $line['material']->id)
            ->map(function (Collection $group) use (&$problems, $leftovers) {
                /** @var Material $material */
                $material = $group->first()['material'];
                $cuts = $group->flatMap(fn (array $line) => collect($line['cuts'])->map(fn (float $length) => [
                    'length' => $length, 'label' => $line['part'].' · '.$line['component'],
                ]))->values()->all();
                $plan = $this->planner->cuttingPlan($material, $cuts, allowNewBars: false);
                $pieces = PipePiece::whereIn('id', collect($plan['assignments'])->pluck('key'))->get()->keyBy('id');
                $cost = collect($plan['assignments'])->sum(fn (array $cut) => ($cut['length'] + $cut['kerf']) * (float) $pieces[$cut['key']]->cost_per_cm);
                $outcomes = $this->outcomes($material, $plan['assignments'], $pieces, $leftovers);

                foreach ($plan['unplaced'] as $cut) {
                    $problems[] = sprintf('%s: tidak ada pipa yang cukup untuk potong %s cm (%s).', $material->label(), qty($cut['length'], 2), $cut['label']);
                }

                return [
                    'material' => $material,
                    'assignments' => $plan['assignments'],
                    'unplaced' => $plan['unplaced'],
                    'outcomes' => $outcomes,
                    'need' => (float) $group->sum('qty'),
                    'cost' => $cost,
                    'waste' => (float) collect($outcomes)->where('status', PipePiece::SCRAP)->sum(fn (array $row) => $row['after'] * (float) $pieces[$row['key']]->cost_per_cm),
                ];
            })->values();

        $materialCost = (float) $units->sum('cost') + (float) $pipes->sum('cost');
        $wasteCost = (float) $pipes->sum('waste');
        $otherCost = $production->formula->otherCostPerUnit() * $qty;
        $services = $production->formula->componentServices($qty);
        $serviceCost = (float) $services->sum('cost');
        $total = $materialCost + $wasteCost + $otherCost + $serviceCost;

        if ($requirements->isEmpty()) {
            $problems[] = 'Formula ini belum punya bahan.';
        }

        return [
            'units' => $units,
            'pipes' => $pipes,
            'ready' => $problems === [],
            'problems' => $problems,
            'material_cost' => $materialCost,
            'waste_cost' => $wasteCost,
            'other_cost' => $otherCost,
            'services' => $services,
            'service_cost' => $serviceCost,
            'total_cost' => $total,
            'hpp' => $qty > 0 ? $total / $qty : 0,
        ];
    }

    /**
     * @param  array<int|string, string>  $leftovers  pilihan sisa per potongan pipa: stok | sampah
     */
    public function post(Production $production, array $leftovers = []): void
    {
        DB::transaction(function () use ($production, $leftovers) {
            $production = Production::lockForUpdate()->findOrFail($production->id);

            if ($production->isPosted()) {
                throw new RuntimeException('Produksi ini sudah dibukukan.');
            }

            $preview = $this->preview($production, $leftovers);

            if (! $preview['ready']) {
                throw new RuntimeException('Belum bisa dibukukan: '.implode(' ', $preview['problems']));
            }

            $date = $production->produced_at;
            $materialCost = 0.0;
            $wasteCost = 0.0;

            foreach ($preview['units'] as $row) {
                $material = $row['material'];

                foreach ($row['lines'] as $line) {
                    $label = $line['part'].' · '.$line['component'];
                    $cost = $this->stock->issueUnitsFromAnywhere($material, (float) $line['qty'], 'pemakaian', $production, $date, $production->production_number.' · '.$label);
                    $materialCost += $cost;
                    $production->usages()->create([
                        'material_id' => $material->id, 'part_name' => $line['part'], 'component_name' => $line['component'],
                        'qty' => $line['qty'], 'unit_cost' => $line['qty'] > 0 ? $cost / $line['qty'] : 0, 'total_cost' => $cost,
                    ]);
                }
            }

            $offcut = Warehouse::forType(Warehouse::OFFCUT);
            $sequence = 0;

            foreach ($preview['pipes'] as $row) {
                /** @var Material $material */
                $material = $row['material'];
                $usage = [];
                $touched = [];
                $destination = collect($row['outcomes'])->pluck('status', 'key');

                foreach ($row['assignments'] as $cut) {
                    $piece = PipePiece::with('warehouse', 'material')->lockForUpdate()->findOrFail($cut['key']);
                    $used = round($cut['length'] + $cut['kerf'], 2);
                    $cost = $used * (float) $piece->cost_per_cm;

                    ProductionCut::create([
                        'production_id' => $production->id, 'pipe_piece_id' => $piece->id, 'label' => $cut['label'],
                        'length_cm' => $cut['length'], 'kerf_cm' => $cut['kerf'], 'before_warehouse_id' => $piece->warehouse_id,
                        'before_length_cm' => $piece->length_cm, 'before_status' => $piece->status, 'cost' => $cost, 'sequence' => ++$sequence,
                    ]);

                    $this->stock->recordPiece($piece, $piece->warehouse, 'pemakaian', 0, $used, $production, $date, $production->production_number.' · '.$cut['label'].' '.qty($cut['length'], 2).' cm');
                    $piece->forceFill(['length_cm' => max((float) $piece->length_cm - $used, 0)])->save();

                    $usage[$cut['label']] = [
                        'qty' => ($usage[$cut['label']]['qty'] ?? 0) + $cut['length'],
                        'cost' => ($usage[$cut['label']]['cost'] ?? 0) + $cost,
                    ];
                    $touched[$piece->id] = $piece;
                    $materialCost += $cost;
                }

                foreach ($touched as $piece) {
                    $piece->refresh()->load('warehouse', 'material');
                    $remaining = (float) $piece->length_cm;

                    if ($remaining <= 0) {
                        $piece->forceFill(['status' => PipePiece::USED_UP])->save();
                    } elseif (($destination[$piece->id] ?? null) === PipePiece::SCRAP) {
                        $this->stock->recordPiece($piece, $piece->warehouse, 'afkir', 0, $remaining, $production, $date, $piece->code.' sisa '.qty($remaining, 2).' cm dibuang');
                        $wasteCost += $piece->value();
                        $piece->forceFill(['status' => PipePiece::SCRAP])->save();
                    } elseif ($piece->warehouse_id !== $offcut->id) {
                        $this->stock->recordPiece($piece, $piece->warehouse, 'sisa', 0, $remaining, $production, $date, $piece->code.' sisa ke '.$offcut->name);
                        $this->stock->recordPiece($piece, $offcut, 'sisa', $remaining, 0, $production, $date, $piece->code.' sisa dari '.$piece->warehouse->name);
                        $piece->forceFill(['warehouse_id' => $offcut->id])->save();
                    }
                }

                foreach ($usage as $label => $line) {
                    [$part, $component] = array_pad(explode(' · ', $label, 2), 2, '');
                    $production->usages()->create([
                        'material_id' => $material->id, 'part_name' => $part, 'component_name' => $component,
                        'qty' => $line['qty'], 'unit_cost' => $line['qty'] > 0 ? $line['cost'] / $line['qty'] : 0, 'total_cost' => $line['cost'],
                    ]);
                }

                $this->stock->recalculate($material);
            }

            $otherCost = $production->formula->otherCostPerUnit() * (float) $production->qty;

            // Komponen yang dijasakan: jadi biaya produksi dan tagihan ke tiap vendor.
            $serviceCost = 0.0;
            foreach ($preview['services']->groupBy('vendor') as $vendor => $lines) {
                $amount = (float) $lines->sum('cost');
                $serviceCost += $amount;

                if ($amount <= 0) {
                    continue;
                }

                MaterialPurchase::create([
                    'invoice_number' => DocumentNumber::next('material_purchases', Carbon::parse($date)),
                    'kind' => 'jasa',
                    'purchased_at' => $date,
                    'supplier_id' => Supplier::resolve($vendor)?->id,
                    'supplier' => $vendor,
                    'production_id' => $production->id,
                    'total' => $amount,
                    'notes' => 'Jasa '.$lines->map(fn ($line) => trim(($line['process'] ? $line['process'].' ' : '').$line['component']).' '.qty($line['qty']).' × '.rupiah($line['rate']))->implode(', ')
                        .' · '.$production->production_number,
                ]);
            }

            $total = $materialCost + $wasteCost + $otherCost + $serviceCost;
            $hpp = $total / (float) $production->qty;

            $production->forceFill([
                'status' => Production::POSTED, 'posted_at' => now(),
                'service_cost' => $serviceCost,
                'material_cost' => $materialCost, 'waste_cost' => $wasteCost, 'other_cost' => $otherCost,
                'total_cost' => $total, 'hpp_per_unit' => $hpp,
            ])->save();

            $this->receiveOutput($production);
            $production->planLine?->plan?->refreshStatus();
        });
    }

    /**
     * Terima hasil proses vendor (boleh bertahap). Hasil masuk Gudang Setengah
     * Jadi dengan harga bahan + jasa; jasanya menjadi tagihan (hutang) vendor.
     */
    public function receive(Production $production, float $qty, string $date, float $serviceCostPerUnit, ?string $notes = null): ProductionReceipt
    {
        return DB::transaction(function () use ($production, $qty, $date, $serviceCostPerUnit, $notes) {
            $production = Production::with('formula.outputMaterial')->lockForUpdate()->findOrFail($production->id);

            if (! $production->isVendor() || ! $production->isPosted()) {
                throw new RuntimeException('Hanya produksi vendor yang sudah dikirim yang bisa diterima.');
            }

            if ($qty <= 0 || $qty > $production->outstandingQty() + 0.0001) {
                throw new RuntimeException('Jumlah diterima harus antara 1 dan '.qty($production->outstandingQty()).' (yang masih di vendor).');
            }

            $unitCost = (float) $production->hpp_per_unit + $serviceCostPerUnit;
            $receipt = $production->receipts()->create([
                'received_at' => $date, 'qty' => $qty, 'service_cost_per_unit' => $serviceCostPerUnit,
                'unit_cost' => $unitCost, 'notes' => $notes,
            ]);

            $material = $production->formula->outputMaterial;
            $this->stock->receiveUnits($material, Warehouse::forType(Warehouse::SEMI), $qty, $unitCost, 'terima_vendor', $receipt, $date,
                $production->production_number.' · dari '.$production->vendor.($production->process ? ' ('.$production->process.')' : ''));

            if ($serviceCostPerUnit > 0) {
                $bill = MaterialPurchase::create([
                    'invoice_number' => DocumentNumber::next('material_purchases', Carbon::parse($date)),
                    'kind' => 'jasa',
                    'purchased_at' => $date,
                    'supplier_id' => Supplier::resolve($production->vendor)?->id,
                    'supplier' => $production->vendor,
                    'total' => $qty * $serviceCostPerUnit,
                    'notes' => 'Jasa '.($production->process ?: 'vendor').' '.qty($qty).' × '.$material->label().' · '.$production->production_number,
                ]);
                $receipt->forceFill(['material_purchase_id' => $bill->id])->save();
            }

            $production->forceFill(['received_qty' => (float) $production->received_qty + $qty])->save();

            return $receipt;
        });
    }

    /** Hapus penerimaan vendor: stok hasil dan tagihan jasanya dibatalkan. */
    public function deleteReceipt(ProductionReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt) {
            $production = $receipt->production()->with('formula.outputMaterial.stocks')->firstOrFail();
            $material = $production->formula->outputMaterial;

            if ($material->stockIn(Warehouse::forType(Warehouse::SEMI)) + 0.0001 < (float) $receipt->qty) {
                throw new RuntimeException('Barang dari penerimaan ini sudah terpakai, jadi penerimaannya tidak bisa dihapus.');
            }

            if ($receipt->bill && $receipt->bill->payments()->exists()) {
                throw new RuntimeException('Tagihan jasa vendor dari penerimaan ini sudah dibayar. Hapus pembayarannya dulu.');
            }

            $this->stock->forget($receipt);
            $receipt->bill?->delete();
            $production->forceFill(['received_qty' => max((float) $production->received_qty - (float) $receipt->qty, 0)])->save();
            $receipt->delete();
        });
    }

    /** Hasil produksi masuk gudangnya: knalpot ke Finish Good, setengah jadi ke Gudang Setengah Jadi. */
    private function receiveOutput(Production $production): void
    {
        if ($production->isVendor()) {
            return;
        }

        if ($production->formula->isSemiFinished()) {
            $this->stock->receiveUnits($production->formula->outputMaterial, Warehouse::forType(Warehouse::SEMI), (float) $production->qty,
                (float) $production->hpp_per_unit, 'hasil', $production, $production->produced_at, $production->production_number.' · hasil produksi');

            return;
        }

        $this->inventory->receiveProduction($production);
    }

    /** Batalkan pembukuan: stok bahan, potongan pipa, dan stok knalpot jadi dikembalikan. */
    public function cancel(Production $production): void
    {
        DB::transaction(function () use ($production) {
            $production = Production::with('cuts.piece', 'item')->lockForUpdate()->findOrFail($production->id);

            if (! $production->isPosted()) {
                throw new RuntimeException('Produksi ini masih draf.');
            }

            $lastOwnMovement = (int) MaterialMovement::where('source_type', $production->getMorphClass())->where('source_id', $production->id)->max('id');
            $pieceIds = $production->cuts->pluck('pipe_piece_id')->unique();
            $laterUse = MaterialMovement::whereIn('pipe_piece_id', $pieceIds)->where('id', '>', $lastOwnMovement)->exists();

            if ($laterUse) {
                throw new RuntimeException('Potongan pipa dari produksi ini sudah dipakai lagi oleh transaksi lain. Batalkan transaksi yang lebih baru dulu.');
            }

            if ($production->isVendor()) {
                if ($production->receipts()->exists()) {
                    throw new RuntimeException('Sebagian hasil sudah diterima dari vendor. Hapus penerimaannya dulu.');
                }
            } elseif ($production->formula->isSemiFinished()) {
                $output = $production->formula->outputMaterial()->with('stocks')->firstOrFail();

                if ($output->stockIn(Warehouse::forType(Warehouse::SEMI)) + 0.0001 < (float) $production->qty) {
                    throw new RuntimeException("Stok {$output->label()} dari produksi ini sudah terpakai.");
                }
            } else {
                $this->inventory->revertProduction($production);
            }

            foreach ($production->cuts->groupBy('pipe_piece_id') as $cuts) {
                $first = $cuts->sortBy('sequence')->first();
                $first->piece->forceFill([
                    'warehouse_id' => $first->before_warehouse_id,
                    'length_cm' => $first->before_length_cm,
                    'status' => $first->before_status,
                ])->save();
            }

            // Tagihan jasa komponen ikut dibatalkan, asal belum dibayar.
            $bills = $production->serviceBills()->get();
            if ($bills->contains(fn (MaterialPurchase $bill) => (float) $bill->paid_amount > 0)) {
                throw new RuntimeException('Tagihan jasa vendor dari produksi ini sudah dibayar sebagian. Hapus pembayarannya dulu.');
            }
            $bills->each->delete();

            $production->cuts()->delete();
            $production->usages()->delete();
            $this->stock->forget($production);

            $production->forceFill([
                'status' => Production::DRAFT, 'posted_at' => null, 'service_cost' => 0,
                'material_cost' => 0, 'waste_cost' => 0, 'other_cost' => 0, 'total_cost' => 0, 'hpp_per_unit' => 0,
            ])->save();

            $production->planLine?->plan?->refreshStatus();
        });
    }

    /**
     * Keadaan akhir tiap potongan yang tersentuh rencana potong.
     *
     * Sisa yang cukup panjang disarankan kembali ke stok (Gudang Stok Sisa),
     * yang lebih pendek dari batas sisa minimal disarankan dibuang; pilihan
     * pengguna menggantikan saran itu.
     *
     * @param  list<array<string, mixed>>  $assignments
     * @param  Collection<int, PipePiece>  $pieces
     * @param  array<int|string, string>  $leftovers
     * @return list<array{key: int, code: string, material: string, before: float, after: float, value: float, suggested: string, status: string, from_offcut: bool}>
     */
    private function outcomes(Material $material, array $assignments, Collection $pieces, array $leftovers = []): array
    {
        $offcut = Warehouse::forType(Warehouse::OFFCUT)->id;

        return collect($assignments)->groupBy('key')->map(function (Collection $cuts, $key) use ($material, $pieces, $offcut, $leftovers) {
            $after = (float) $cuts->last()['after'];
            $suggested = $after < (float) $material->min_reusable_cm ? PipePiece::SCRAP : 'sisa';
            $status = match (true) {
                $after <= 0 => PipePiece::USED_UP,
                ($leftovers[$key] ?? null) === 'sampah' => PipePiece::SCRAP,
                ($leftovers[$key] ?? null) === 'stok' => 'sisa',
                default => $suggested,
            };

            return [
                'key' => (int) $key,
                'code' => $cuts->first()['code'],
                'material' => $material->label(),
                'before' => (float) $cuts->first()['before'],
                'after' => $after,
                'value' => $after * (float) $pieces[$key]->cost_per_cm,
                'suggested' => $after <= 0 ? PipePiece::USED_UP : $suggested,
                'status' => $status,
                'from_offcut' => $pieces[$key]->warehouse_id === $offcut,
            ];
        })->values()->all();
    }
}
