<?php

namespace App\Support;

use App\Models\Formula;
use App\Models\FormulaLine;

/**
 * Hitungan HPP sebuah formula berikut harga jualnya.
 *
 * Dipisahkan dari halamannya supaya bisa diuji tanpa menyentuh layar: seluruh
 * isinya angka uang, dan angka uang yang hanya diperiksa lewat mata di browser
 * cepat atau lambat akan salah tanpa ada yang tahu.
 *
 * Semua nilai "per unit" mengacu pada satu satuan keluaran formula — satu set,
 * satu pcs — bukan pada sekali resep.
 */
class HitungHpp
{
    public function __construct(
        public readonly Formula $formula,
        public readonly float $jumlah = 1,
        public readonly float $markupPersen = 0,
        public readonly float $markupResellerPersen = 0,
        public readonly float $biayaAdmin = 0,
        public readonly float $biayaOngkir = 0,
        public readonly float $potonganPersen = 0,
    ) {}

    // ------------------------------------------------------------------- hpp

    public function bahanPerUnit(): float
    {
        return $this->formula->materialCostPerUnit();
    }

    public function jasaPerUnit(): float
    {
        return $this->formula->serviceCostPerUnit();
    }

    public function hppPerUnit(): float
    {
        return $this->formula->totalCostPerUnit();
    }

    /** Modal untuk seluruh pesanan yang mau dibuat. */
    public function hppTotal(): float
    {
        return $this->hppPerUnit() * $this->jumlah;
    }

    // ----------------------------------------------------------- harga jual

    /** Markup dihitung dari HPP: 40% berarti HPP ditambah 40% HPP. */
    public function hargaJual(): float
    {
        return $this->hppPerUnit() * (1 + $this->markupPersen / 100);
    }

    public function hargaReseller(): float
    {
        return $this->hppPerUnit() * (1 + $this->markupResellerPersen / 100);
    }

    public function laba(float $harga): float
    {
        return $harga - $this->hppPerUnit();
    }

    /**
     * Margin, bukan markup: bagian laba dari uang yang masuk.
     *
     * Markup 40% hanya bermargin 28,6%, dan mengira keduanya sama adalah cara
     * paling lazim mematok harga yang terlalu murah.
     */
    public function margin(float $harga): float
    {
        return $harga > 0 ? $this->laba($harga) / $harga * 100 : 0.0;
    }

    // ----------------------------------------------------------- marketplace

    /**
     * Harga yang perlu dipasang di toko online.
     *
     * Potongan aplikasi diambil dari harga terpasang, bukan dari yang tersisa —
     * jadi harganya tidak cukup ditambah sebesar potongan itu, melainkan dibagi
     * sisanya. Menambahkan 10% pada harga yang dipotong 10% selalu kurang.
     */
    public function hargaPasang(): float
    {
        $sisa = 1 - $this->potonganPersen / 100;

        if ($sisa <= 0) {
            return 0.0;
        }

        return ($this->hargaJual() + $this->biayaAdmin + $this->biayaOngkir) / $sisa;
    }

    /** Potongan aplikasi dalam rupiah, dari harga terpasang. */
    public function potonganRupiah(): float
    {
        return $this->hargaPasang() * $this->potonganPersen / 100;
    }

    /** Yang benar-benar masuk kantong — mestinya sama dengan harga jual umum. */
    public function diterimaBersih(): float
    {
        return $this->hargaPasang() - $this->potonganRupiah() - $this->biayaAdmin - $this->biayaOngkir;
    }

    // -------------------------------------------------------------- tampilan

    /** Rincian dari mana modalnya datang, sampai ke tiap komponen. */
    public function rincianHtml(): string
    {
        $html = $this->tabelBahan().$this->tabelJasa();

        $html .= '<div class="hpp-total">'
            .'<span>HPP <strong>'.$this->rp($this->hppPerUnit()).'</strong> per '.e($this->formula->output_unit).'</span>'
            .'<span class="hpp-samping">bahan '.$this->rp($this->bahanPerUnit())
            .' + jasa '.$this->rp($this->jasaPerUnit()).'</span>'
            .'<span class="hpp-samping">'.$this->angka($this->jumlah).' '.e($this->formula->output_unit)
            .' = <strong>'.$this->rp($this->hppTotal()).'</strong></span>'
            .'</div>';

        return $html;
    }

    public function hargaHtml(): string
    {
        return '<div class="hpp-kartu-baris">'
            .$this->kartuHarga('Harga Umum', $this->hargaJual(), $this->markupPersen)
            .$this->kartuHarga('Harga Reseller', $this->hargaReseller(), $this->markupResellerPersen)
            .'</div>';
    }

    public function marketplaceHtml(): string
    {
        $baris = [
            ['Harga dipasang', $this->hargaPasang(), true],
            ['Potongan aplikasi '.$this->angka($this->potonganPersen).'%', -$this->potonganRupiah(), false],
            ['Biaya admin', -$this->biayaAdmin, false],
            ['Ongkir ditanggung', -$this->biayaOngkir, false],
            ['Diterima bersih', $this->diterimaBersih(), true],
            ['HPP', -$this->hppPerUnit(), false],
            ['Laba per unit', $this->diterimaBersih() - $this->hppPerUnit(), true],
        ];

        $isi = '';

        foreach ($baris as [$label, $nilai, $tebal]) {
            $isi .= '<tr'.($tebal ? ' class="hpp-tebal"' : '').'>'
                .'<td>'.e($label).'</td>'
                .'<td class="hpp-kanan">'.$this->rp($nilai).'</td>'
                .'</tr>';
        }

        return '<table class="hpp-tabel hpp-sempit"><tbody>'.$isi.'</tbody></table>';
    }

    // ------------------------------------------------------------- pembantu

    private function tabelBahan(): string
    {
        $terisi = $this->formula->lines->filter(fn (FormulaLine $l) => $l->item !== null);

        if ($terisi->isEmpty()) {
            return '<p class="hpp-kosong">Belum ada komponen yang bahannya ditentukan.</p>';
        }

        $isi = '';

        foreach ($terisi as $baris) {
            $isi .= '<tr>'
                .'<td>'.e($baris->component?->fullName() ?? '—').'</td>'
                .'<td>'.e($baris->item?->name ?? '—').'</td>'
                .'<td class="hpp-kanan">'.e($baris->displayQty()).'</td>'
                .'<td class="hpp-kanan">'.$this->rp($baris->subtotal()).'</td>'
                .'</tr>';
        }

        $belum = $this->formula->lines->count() - $terisi->count();
        $catatan = $belum > 0
            ? '<tr class="hpp-catatan"><td colspan="4">'.$belum.' komponen lain belum ada bahannya — belum ikut dihitung.</td></tr>'
            : '';

        return '<table class="hpp-tabel">'
            .'<thead><tr><th>Komponen</th><th>Bahan</th><th class="hpp-kanan">Kebutuhan</th><th class="hpp-kanan">Biaya</th></tr></thead>'
            .'<tbody>'.$isi.$catatan.'</tbody>'
            .'<tfoot><tr class="hpp-tebal"><td colspan="3">Subtotal bahan — sekali resep</td>'
            .'<td class="hpp-kanan">'.$this->rp($this->formula->materialCost()).'</td></tr></tfoot>'
            .'</table>';
    }

    private function tabelJasa(): string
    {
        if ($this->formula->services->isEmpty()) {
            return '<p class="hpp-kosong">Tanpa biaya lain-lain — resep ini tidak memakai jasa.</p>';
        }

        $isi = '';

        foreach ($this->formula->services as $jasa) {
            $isi .= '<tr>'
                .'<td>'.e($jasa->service?->name ?? '—').'</td>'
                .'<td class="hpp-kanan">'.e($jasa->service?->displayRate() ?? '—').'</td>'
                .'<td class="hpp-kanan">'.e($jasa->displayQty()).'</td>'
                .'<td class="hpp-kanan">'.$this->rp($jasa->subtotal()).'</td>'
                .'</tr>';
        }

        return '<table class="hpp-tabel">'
            .'<thead><tr><th>Jasa</th><th class="hpp-kanan">Tarif</th><th class="hpp-kanan">Dipakai</th><th class="hpp-kanan">Biaya</th></tr></thead>'
            .'<tbody>'.$isi.'</tbody>'
            .'<tfoot><tr class="hpp-tebal"><td colspan="3">Subtotal jasa — sekali resep</td>'
            .'<td class="hpp-kanan">'.$this->rp($this->formula->serviceCost()).'</td></tr></tfoot>'
            .'</table>';
    }

    private function kartuHarga(string $judul, float $harga, float $markup): string
    {
        return '<div class="hpp-kartu">'
            .'<div class="hpp-kartu-judul">'.e($judul).'</div>'
            .'<div class="hpp-kartu-angka">'.$this->rp($harga).'</div>'
            .'<div class="hpp-kartu-kaki">markup '.$this->angka($markup).'%'
            .' · laba '.$this->rp($this->laba($harga))
            .' · margin '.$this->angka($this->margin($harga)).'%</div>'
            .'</div>';
    }

    private function rp(float $angka): string
    {
        $tanda = $angka < 0 ? '−' : '';

        return $tanda.'Rp'.number_format(abs($angka), 0, ',', '.');
    }

    private function angka(float $n): string
    {
        $teks = number_format($n, 1, ',', '.');

        return str_ends_with($teks, ',0') ? substr($teks, 0, -2) : $teks;
    }
}
