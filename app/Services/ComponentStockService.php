<?php

namespace App\Services;

use App\Models\Component;
use App\Models\ComponentMovement;
use App\Models\Material;
use App\Models\MaterialMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mutasi gudang setengah jadi. Komponen yang dibuat bisa langsung memotong
 * stok bahan baku sesuai bahan penyusunnya di Master Komponen.
 */
class ComponentStockService
{
    /**
     * @param  string  $type  masuk | keluar | penyesuaian (qty bertanda untuk penyesuaian)
     */
    public function record(Component $component, string $type, float $qty, string $date, ?string $notes = null, bool $consumeMaterials = false): ComponentMovement
    {
        if ($qty == 0) {
            throw new InvalidArgumentException('Qty tidak boleh 0.');
        }

        if ($type !== 'penyesuaian' && $qty < 0) {
            throw new InvalidArgumentException('Qty harus lebih dari 0.');
        }

        [$in, $out] = match ($type) {
            'masuk' => [$qty, 0.0],
            'keluar' => [0.0, $qty],
            'penyesuaian' => [max($qty, 0), max(-$qty, 0)],
            default => throw new InvalidArgumentException('Jenis mutasi tidak dikenal.'),
        };

        $consumeMaterials = $consumeMaterials && $type === 'masuk';

        return DB::transaction(function () use ($component, $type, $in, $out, $date, $notes, $consumeMaterials) {
            $movement = $component->movements()->create([
                'type' => $type,
                'qty_in' => $in,
                'qty_out' => $out,
                'consumes_materials' => $consumeMaterials,
                'moved_at' => Carbon::parse($date)->toDateString(),
                'notes' => $notes,
            ]);

            if ($consumeMaterials) {
                foreach ($component->materials()->with('material')->get() as $line) {
                    MaterialMovement::create([
                        'material_id' => $line->material_id,
                        'type' => 'component',
                        'qty_out' => (float) $line->qty * $in,
                        'unit_cost' => (float) $line->material->cost_price,
                        'source_type' => ComponentMovement::class,
                        'source_id' => $movement->id,
                        'moved_at' => $movement->moved_at,
                        'notes' => 'Dibuat jadi '.$component->name,
                    ]);
                    $line->material->recalculateStock();
                }
            }

            $component->recalculateStock();

            return $movement->refresh();
        });
    }

    /** Hapus mutasi; bahan baku yang sempat terpotong dikembalikan. */
    public function delete(ComponentMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $materialIds = $movement->materialMovements()->pluck('material_id');
            $movement->materialMovements()->delete();
            $component = $movement->component;
            $movement->delete();

            Material::whereIn('id', $materialIds->unique())->get()->each->recalculateStock();
            $component?->recalculateStock();
        });
    }
}
