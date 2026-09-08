<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Resep produksi (BOM) sekaligus mesin hitung HPP.
 *
 * Semua angka diturunkan saat dipanggil, bukan disimpan: begitu harga pipa di
 * master bahan naik, HPP semua formula yang memakainya ikut menyesuaikan tanpa
 * perlu diedit satu per satu. Angka yang dibekukan hanya ada di nota produksi
 * yang sudah dibukukan, supaya HPP historis tidak berubah.
 *
 * Perhatikan satuan: qty pada baris formula berlaku untuk SATU KALI resep
 * (menghasilkan output_qty unit), bukan per unit.
 */
class Formula extends Model
{
    protected $fillable = [
        'code', 'name', 'motorcycle_model_id', 'item_id', 'output_qty', 'output_unit',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_qty' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function motorcycleModel(): BelongsTo
    {
        return $this->belongsTo(MotorcycleModel::class);
    }

    /**
     * Barang jual yang dihasilkan formula ini.
     *
     * Kalau diisi, nota produksi yang dibukukan akan menambah stok barang ini
     * dengan harga pokok = HPP hasil perhitungan, sehingga knalpot yang selesai
     * dibuat langsung bisa dijual lewat menu Penjualan.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(FormulaMaterial::class)->orderBy('sort_order');
    }

    public function costs(): HasMany
    {
        return $this->hasMany(FormulaCost::class)->orderBy('sort_order');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(FormulaMachine::class)->orderBy('sort_order');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(Production::class);
    }

    // ------------------------------------------------------------- struktur BOM

    /**
     * Bahan dikelompokkan per bagian resep, urut sesuai alur kerja bengkel.
     *
     * @return Collection<string, Collection<int, FormulaMaterial>>
     */
    public function materialsByGroup()
    {
        $urutan = array_keys(FormulaMaterial::BOM_GROUPS);

        return $this->materials
            ->groupBy('bom_group')
            ->sortBy(fn ($lines, $group) => array_search($group, $urutan, true));
    }

    /**
     * Biaya per bagian resep.
     *
     * @return Collection<string, Collection<int, FormulaCost>>
     */
    public function costsByGroup()
    {
        $urutan = array_keys(FormulaMaterial::BOM_GROUPS);

        return $this->costs
            ->groupBy('bom_group')
            ->sortBy(fn ($lines, $group) => array_search($group, $urutan, true));
    }

    /**
     * Biaya per bagian — menjawab "silencer menghabiskan berapa".
     *
     * @return array<string, array{bahan: float, jasa: float, total: float}>
     */
    public function costByGroup(): array
    {
        $hasil = [];

        foreach ($this->materialsByGroup() as $group => $lines) {
            $hasil[$group]['bahan'] = $lines->sum(fn (FormulaMaterial $l) => $l->subtotal());
        }

        foreach ($this->costsByGroup() as $group => $lines) {
            $hasil[$group]['jasa'] = $lines->sum(fn (FormulaCost $l) => $l->subtotal());
        }

        foreach ($this->machines->groupBy('bom_group') as $group => $lines) {
            $hasil[$group]['mesin'] = $lines->sum(fn (FormulaMachine $l) => $l->subtotal());
        }

        foreach ($hasil as $group => $isi) {
            $hasil[$group]['bahan'] ??= 0.0;
            $hasil[$group]['jasa'] ??= 0.0;
            $hasil[$group]['mesin'] ??= 0.0;
            $hasil[$group]['total'] = $hasil[$group]['bahan']
                + $hasil[$group]['jasa']
                + $hasil[$group]['mesin'];
        }

        return $hasil;
    }

    // ------------------------------------------------------------------ HPP

    /**
     * Biaya bahan untuk satu kali resep, sudah termasuk susut.
     *
     * Mendelegasikan ke FormulaMaterial::subtotal() supaya rumusnya hanya ada
     * di satu tempat — lihat catatan di serviceCost().
     */
    public function materialCost(): float
    {
        return $this->materials->sum(fn (FormulaMaterial $line) => $line->subtotal());
    }

    /**
     * Biaya jasa untuk satu kali resep.
     *
     * Sengaja mendelegasikan ke FormulaCost::subtotal() dan tidak menghitung
     * sendiri — sempat ada bug karena rumusnya ditulis dua kali, lalu hanya
     * satu yang diperbarui ketika tarif per jam ditambahkan.
     */
    public function serviceCost(): float
    {
        return $this->costs->sum(fn (FormulaCost $line) => $line->subtotal());
    }

    /** Biaya pemakaian mesin — penyusutan + listrik + maintenance. */
    public function machineCost(): float
    {
        return $this->machines->sum(fn (FormulaMachine $line) => $line->subtotal());
    }

    /**
     * Bahan penolong: kawat las, gas, amplas, compound poles.
     *
     * Sudah termasuk di materialCost(); dipisah supaya kartu HPP bisa
     * menempatkannya di overhead pabrik seperti lazimnya format harga pokok
     * produksi, bukan di bahan baku.
     */
    public function consumableCost(): float
    {
        return $this->materials
            ->where('bom_group', FormulaMaterial::GRUP_PENOLONG)
            ->sum(fn (FormulaMaterial $line) => $line->subtotal());
    }

    /** Bahan yang benar-benar menempel di produk. */
    public function directMaterialCost(): float
    {
        return $this->materialCost() - $this->consumableCost();
    }

    /**
     * Berapa rupiah yang hilang jadi sisa potong lembaran.
     *
     * Sudah termasuk di dalam materialCost(); dipisah hanya untuk ditampilkan,
     * supaya terlihat berapa yang bisa dihemat dengan menata pola potong.
     */
    public function nestingWasteCost(): float
    {
        return $this->materials->sum(fn (FormulaMaterial $line) => $line->nestingWasteCost());
    }

    /** Total menit kerja satu resep, untuk melihat beban waktunya. */
    public function totalMinutes(): float
    {
        return $this->costs->sum(fn (FormulaCost $line) => (float) $line->minutes);
    }

    /**
     * Beban biaya tetap bengkel untuk satu kali resep.
     *
     * Diambil dari total overhead bulanan dibagi target produksi bulanan, lalu
     * dikali jumlah unit yang dihasilkan resep ini. Angka yang sama untuk semua
     * formula, karena sewa dan listrik penerangan tidak peduli knalpot mana
     * yang sedang dikerjakan.
     */
    public function overheadCost(): float
    {
        return OverheadItem::perUnit() * (float) $this->output_qty;
    }

    /** Total biaya satu kali resep. */
    public function totalCost(): float
    {
        return $this->materialCost()
            + $this->serviceCost()
            + $this->machineCost()
            + $this->overheadCost();
    }

    /** Inilah angka yang dicari: modal untuk satu knalpot. */
    public function hppPerUnit(): float
    {
        $out = (float) $this->output_qty;

        return $out > 0 ? $this->totalCost() / $out : 0.0;
    }

    /**
     * @return array<string, float>
     */
    public function breakdown(): array
    {
        $out = max((float) $this->output_qty, 0.0001);

        return [
            'bahan' => $this->materialCost(),
            'jasa' => $this->serviceCost(),
            'mesin' => $this->machineCost(),
            'overhead' => $this->overheadCost(),
            'total' => $this->totalCost(),
            'per_unit' => $this->hppPerUnit(),
            'menit' => $this->totalMinutes(),
            'sisa_potong' => $this->nestingWasteCost(),
            'bahan_per_unit' => $this->materialCost() / $out,
            'jasa_per_unit' => $this->serviceCost() / $out,
            'mesin_per_unit' => $this->machineCost() / $out,
            'overhead_per_unit' => $this->overheadCost() / $out,
        ];
    }

    // ------------------------------------------------------------ harga jual

    /**
     * Harga jual per tingkatan, lengkap dengan laba bersihnya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function priceList(): array
    {
        $hpp = $this->hppPerUnit();

        return PriceTier::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PriceTier $tier) => ['tingkat' => $tier] + $tier->breakdown($hpp))
            ->all();
    }

    // ------------------------------------------------- kebutuhan untuk target

    /**
     * Berapa resep harus dijalankan untuk mendapat sejumlah unit.
     *
     * Dibulatkan ke atas: resep yang menghasilkan 2 set tidak bisa dijalankan
     * setengah kali untuk mendapat 1 set.
     */
    public function batchFor(float $targetUnit): float
    {
        return ceil($targetUnit / max((float) $this->output_qty, 0.001));
    }

    /**
     * Kebutuhan bahan untuk membuat sejumlah unit, lengkap dengan cek stok.
     *
     * Inilah yang menjawab "kalau mau bikin 20 biji, butuh apa saja dan
     * stoknya cukup tidak". Dipakai form nota produksi maupun kalkulator,
     * supaya angkanya tidak mungkin berbeda antar layar.
     *
     * @return array{
     *     unit: float, batch: float, hasil: float,
     *     baris: array<int, array<string, mixed>>,
     *     kurang: array<int, array<string, mixed>>,
     *     biaya_bahan: float, biaya_belanja: float, cukup: bool
     * }
     */
    public function requirementFor(float $targetUnit): array
    {
        $this->loadMissing('materials.material');

        $batch = $this->batchFor($targetUnit);
        $baris = [];
        $biayaBahan = 0.0;

        foreach ($this->materials as $line) {
            $m = $line->material;

            if (! $m) {
                continue;
            }

            $butuh = $line->effectiveQty() * $batch;
            $ada = (float) $m->stock;
            $kurang = max(0.0, $butuh - $ada);
            $biaya = $butuh * $m->basePrice();
            $biayaBahan += $biaya;

            // Yang dibeli tetap satuan beli utuh — tidak ada toko yang
            // menjual pipa per milimeter.
            $beli = $kurang > 0 ? (float) ceil($m->toPurchase($kurang)) : 0.0;

            $baris[] = [
                'material' => $m,
                'bagian' => FormulaMaterial::BOM_GROUPS[$line->bom_group] ?? $line->bom_group,
                'butuh' => $butuh,
                'butuh_label' => $m->formatBase($butuh),
                'tersedia' => $ada,
                'tersedia_label' => $m->formatBase($ada),
                'kurang' => $kurang,
                'kurang_label' => $kurang > 0 ? $m->formatBase($kurang) : null,
                'cukup' => $kurang <= 0,
                'beli' => $beli,
                'beli_label' => $beli > 0
                    ? rtrim(rtrim(number_format($beli, 2, ',', '.'), '0'), ',').' '.$m->unit
                    : null,
                'harga_satuan' => (float) $m->cost_price,
                'biaya_beli' => $beli * (float) $m->cost_price,
                'biaya' => $biaya,
            ];
        }

        $kurang = array_values(array_filter($baris, fn ($r) => ! $r['cukup']));

        return [
            'unit' => $targetUnit,
            'batch' => $batch,
            'hasil' => $batch * (float) $this->output_qty,
            'baris' => $baris,
            'kurang' => $kurang,
            'biaya_bahan' => $biayaBahan,
            'biaya_belanja' => array_sum(array_column($kurang, 'biaya_beli')),
            'cukup' => $kurang === [],
        ];
    }

    // ------------------------------------------------------------- kapasitas

    /**
     * Berapa unit yang masih bisa dibuat dari stok bahan yang ada sekarang.
     *
     * Dibatasi bahan yang paling sedikit — percuma punya 100 plat kalau
     * pipanya cuma cukup untuk 3 knalpot.
     *
     * @return array{unit: float, batch: float, pembatas: ?string, rincian: array<int, array<string, mixed>>}
     */
    public function capacity(): array
    {
        $rincian = [];
        $batchTerkecil = null;
        $pembatas = null;

        foreach ($this->materials as $line) {
            $butuh = $line->effectiveQty();

            if ($butuh <= 0) {
                continue; // baris tanpa kebutuhan tidak membatasi apa pun
            }

            $tersedia = (float) ($line->material->stock ?? 0);
            $bisa = floor($tersedia / $butuh);

            $rincian[] = [
                'bahan' => $line->material?->name ?? '-',
                'satuan' => $line->material?->baseUnit() ?? '',
                'butuh_per_resep' => $butuh,
                'butuh_label' => $line->material?->formatBase($butuh) ?? (string) $butuh,
                'tersedia' => $tersedia,
                'tersedia_label' => $line->material?->formatBase($tersedia) ?? (string) $tersedia,
                'cukup_untuk_resep' => $bisa,
            ];

            if ($batchTerkecil === null || $bisa < $batchTerkecil) {
                $batchTerkecil = $bisa;
                $pembatas = $line->material?->name;
            }
        }

        $batch = $batchTerkecil ?? 0;

        return [
            'unit' => $batch * (float) $this->output_qty,
            'batch' => (float) $batch,
            'pembatas' => $batch > 0 ? $pembatas : ($pembatas ?? null),
            'rincian' => $rincian,
        ];
    }
}
