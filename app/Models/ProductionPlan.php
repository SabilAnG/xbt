<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rencana produksi beberapa formula sekaligus, lengkap dengan daftar belanjanya.
 *
 * Rencana tidak menyentuh stok. Ia hanya menjumlahkan kebutuhan semua formula
 * yang direncanakan, menguranginya dengan stok yang ada, lalu mengubah sisanya
 * kembali ke satuan beli — karena tidak ada toko yang menjual pipa per
 * milimeter.
 */
class ProductionPlan extends Model
{
    protected $fillable = [
        'plan_number', 'planned_for', 'title', 'material_purchase_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['planned_for' => 'date'];
    }

    /**
     * Hasil requirements() disimpan sebentar.
     *
     * Satu baris tabel memanggilnya empat kali — untuk nilai, warna, deskripsi,
     * dan export — dan tiap panggilan menelusuri seluruh formula beserta
     * bahannya. Tanpa ini daftar rencana jadi lambat tanpa alasan.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $kebutuhanCache = null;

    public function refresh()
    {
        $this->kebutuhanCache = null;

        return parent::refresh();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionPlanLine::class)->orderBy('sort_order');
    }

    /** Nota produksi yang dibuat dari rencana ini. */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    /** Nota pembelian yang dibuat dari daftar belanja rencana ini. */
    public function materialPurchase(): BelongsTo
    {
        return $this->belongsTo(MaterialPurchase::class);
    }

    // ------------------------------------------------------------ kebutuhan

    /**
     * Kebutuhan bahan gabungan seluruh baris rencana.
     *
     * Bahan yang dipakai dua formula dijumlah jadi satu baris — kalau tidak,
     * daftar belanjanya akan menyuruh membeli pipa Ø28 dua kali.
     *
     * @return array<int, array<string, mixed>>
     */
    public function requirements(): array
    {
        return $this->kebutuhanCache ??= $this->hitungKebutuhan();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hitungKebutuhan(): array
    {
        $this->loadMissing('lines.formula.materials.material');

        /** @var array<int, array<string, mixed>> $butuh */
        $butuh = [];

        foreach ($this->lines as $line) {
            $formula = $line->formula;

            if (! $formula) {
                continue;
            }

            $batch = $line->batchCount();

            foreach ($formula->materials as $bahan) {
                $m = $bahan->material;

                if (! $m) {
                    continue;
                }

                $butuh[$m->id] ??= [
                    'material' => $m,
                    'butuh' => 0.0,
                    'dipakai_formula' => [],
                ];

                $jumlah = $bahan->effectiveQty() * $batch;
                $butuh[$m->id]['butuh'] += $jumlah;
                $butuh[$m->id]['dipakai_formula'][] = $formula->name;
            }
        }

        $hasil = [];

        foreach ($butuh as $baris) {
            /** @var Material $m */
            $m = $baris['material'];
            $tersedia = (float) $m->stock;
            $kurang = max(0.0, $baris['butuh'] - $tersedia);

            // Dibulatkan ke atas: setengah batang pipa tidak dijual.
            $beli = $kurang > 0 ? ceil($m->toPurchase($kurang) * 1000) / 1000 : 0.0;
            $beliBulat = $kurang > 0 ? (float) ceil($m->toPurchase($kurang)) : 0.0;

            $hasil[] = [
                'material' => $m,
                'butuh' => $baris['butuh'],
                'butuh_label' => $m->formatBase($baris['butuh']),
                'tersedia' => $tersedia,
                'tersedia_label' => $m->formatBase($tersedia),
                'kurang' => $kurang,
                'kurang_label' => $kurang > 0 ? $m->formatBase($kurang) : null,
                'cukup' => $kurang <= 0,
                // Dua angka: yang persis dibutuhkan, dan yang harus ditebus.
                'beli_pas' => $beli,
                'beli' => $beliBulat,
                'beli_label' => $beliBulat > 0
                    ? rtrim(rtrim(number_format($beliBulat, 2, ',', '.'), '0'), ',').' '.$m->unit
                    : null,
                'harga_satuan' => (float) $m->cost_price,
                'biaya' => $beliBulat * (float) $m->cost_price,
                // Kelebihan karena pembulatan tetap jadi stok, bukan uang hangus.
                'sisa' => $beliBulat > 0 ? $m->toBase($beliBulat) - $kurang : 0.0,
                'formula' => array_values(array_unique($baris['dipakai_formula'])),
            ];
        }

        usort($hasil, fn ($a, $b) => ($b['kurang'] <=> $a['kurang'])
            ?: strcmp($a['material']->name, $b['material']->name));

        return $hasil;
    }

    /**
     * Hanya bahan yang kurang — inilah daftar belanjanya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function shoppingList(): array
    {
        return array_values(array_filter($this->requirements(), fn ($r) => ! $r['cukup']));
    }

    // ------------------------------------------------------------------ uang

    /** Total belanja yang perlu dikeluarkan sekarang. */
    public function shoppingCost(): float
    {
        return array_sum(array_column($this->shoppingList(), 'biaya'));
    }

    /** Modal seluruh rencana bila semuanya jadi — HPP x target. */
    public function productionCost(): float
    {
        return $this->lines->sum(fn (ProductionPlanLine $l) => $l->productionCost());
    }

    public function targetUnits(): float
    {
        return (float) $this->lines->sum(fn (ProductionPlanLine $l) => (float) $l->target_qty);
    }

    /**
     * Perkiraan omzet dan laba bila seluruh rencana terjual di satu tingkatan.
     *
     * @return array<string, float>
     */
    public function revenueAt(?PriceTier $tier): array
    {
        if (! $tier) {
            return ['omzet' => 0.0, 'laba' => 0.0];
        }

        $omzet = 0.0;
        $laba = 0.0;

        foreach ($this->lines as $line) {
            if (! $line->formula) {
                continue;
            }

            $b = $tier->breakdown($line->formula->hppPerUnit());
            $omzet += $b['harga'] * (float) $line->target_qty;
            $laba += $b['laba'] * (float) $line->target_qty;
        }

        return ['omzet' => $omzet, 'laba' => $laba];
    }

    public function isShopped(): bool
    {
        return $this->material_purchase_id !== null;
    }

    public function hasProductions(): bool
    {
        return $this->productions()->exists();
    }
}
