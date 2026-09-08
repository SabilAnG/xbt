<?php

namespace App\Services;

use App\Models\MaterialPurchase;
use App\Models\Production;
use App\Models\ProductionPlan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menerjemahkan rencana produksi jadi dokumen kerja: nota pembelian bahan dan
 * nota produksi.
 *
 * Keduanya dibuat sebagai DRAFT — jumlah dan harga masih bisa disesuaikan
 * sebelum dibukukan. Rencana sendiri tidak pernah menyentuh stok; yang
 * menambah dan mengurangi stok tetap pembukuan nota-nota itu.
 */
class ShoppingListService
{
    public function createPurchase(ProductionPlan $plan, ?int $vendorId = null): MaterialPurchase
    {
        $belanja = $plan->shoppingList();

        if ($belanja === []) {
            throw new RuntimeException(
                'Tidak ada yang perlu dibeli — stok bahan sudah cukup untuk seluruh rencana ini.'
            );
        }

        if ($plan->isShopped()) {
            throw new RuntimeException(sprintf(
                'Rencana ini sudah punya nota pembelian %s. Hapus tautannya dulu bila memang mau membuat nota baru.',
                $plan->materialPurchase?->invoice_number ?? '-'
            ));
        }

        return DB::transaction(function () use ($plan, $belanja, $vendorId) {
            $nota = $this->buatNota(
                $belanja,
                $vendorId,
                'Dibuat dari rencana produksi '.$plan->plan_number
                    .($plan->title ? ' — '.$plan->title : '')
            );

            // Tautan dua arah supaya nanti terlihat rencana mana yang sudah
            // dibelanjakan dan mana yang masih menunggu.
            $plan->forceFill(['material_purchase_id' => $nota->id])->save();

            return $nota;
        });
    }

    /**
     * Nota pembelian dari daftar kekurangan mana pun — rencana produksi
     * maupun satu nota produksi yang bahannya kurang.
     *
     * @param  array<int, array<string, mixed>>  $kurang  baris dari Formula::requirementFor()['kurang']
     */
    public function createPurchaseFromShortage(array $kurang, ?int $vendorId, string $catatan): MaterialPurchase
    {
        if ($kurang === []) {
            throw new RuntimeException('Tidak ada bahan yang kurang — stok sudah cukup.');
        }

        return DB::transaction(fn () => $this->buatNota($kurang, $vendorId, $catatan));
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     */
    private function buatNota(array $baris, ?int $vendorId, string $catatan): MaterialPurchase
    {
        $nota = MaterialPurchase::create([
            'invoice_number' => DocumentNumber::next('material_purchases'),
            'purchased_at' => now()->toDateString(),
            'vendor_id' => $vendorId,
            'status' => 'draft',
            'notes' => $catatan,
        ]);

        foreach ($baris as $r) {
            $nota->items()->create([
                'material_id' => $r['material']->id,
                'qty' => $r['beli'],
                'unit_cost' => $r['harga_satuan'],
            ]);
        }

        $nota->recalculateTotals();

        return $nota->refresh();
    }

    /**
     * Buat nota produksi draft untuk tiap baris rencana.
     *
     * Jumlah resep diambil dari batchCount(), yang sudah dibulatkan ke atas —
     * jadi nota yang dihasilkan memang bisa dijalankan, bukan 3,33 kali resep.
     * Isinya langsung diambil dari formula supaya tidak perlu mengetik ulang.
     *
     * Nota berstatus draft: stok bahan baru berkurang setelah dibukukan.
     *
     * @return Collection<int, Production>
     */
    public function createProductions(ProductionPlan $plan): Collection
    {
        $plan->loadMissing('lines.formula');

        if ($plan->lines->isEmpty()) {
            throw new RuntimeException('Rencana ini belum diisi formula.');
        }

        if ($plan->hasProductions()) {
            throw new RuntimeException(sprintf(
                'Rencana ini sudah punya %d nota produksi. Hapus notanya dulu bila memang mau dibuat ulang.',
                $plan->productions()->count()
            ));
        }

        $posting = app(ProductionPostingService::class);

        return DB::transaction(function () use ($plan, $posting) {
            $dibuat = collect();

            foreach ($plan->lines as $line) {
                if (! $line->formula) {
                    continue;
                }

                $nota = Production::create([
                    'production_number' => DocumentNumber::next('productions'),
                    'produced_at' => $plan->planned_for?->toDateString() ?? now()->toDateString(),
                    'formula_id' => $line->formula_id,
                    'production_plan_id' => $plan->id,
                    'batch_qty' => $line->batchCount(),
                    'status' => 'draft',
                    'notes' => 'Dibuat dari rencana produksi '.$plan->plan_number
                        .($line->notes ? ' — '.$line->notes : ''),
                ]);

                $posting->fillFromFormula($nota);

                $dibuat->push($nota->refresh());
            }

            return $dibuat;
        });
    }
}
