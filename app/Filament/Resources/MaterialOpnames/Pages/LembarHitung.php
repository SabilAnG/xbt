<?php

namespace App\Filament\Resources\MaterialOpnames\Pages;

use App\Filament\Resources\MaterialOpnames\MaterialOpnameResource;
use App\Models\Material;
use App\Models\MaterialOpname;
use App\Models\MaterialStock;
use App\Models\Rack;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Lembar hitung fisik bahan — kertas yang dibawa keliling rak.
 *
 * Bedanya dengan lembar aset: bahan punya dua satuan. Petugas menghitung dalam
 * satuan beli ("5 batang"), sedangkan sistem menyimpan satuan dasar ("30.000
 * mm"). Karena itu lembar ini menyediakan dua kolom tulis — utuh dan sisa —
 * plus faktor konversinya, supaya angka yang dimasukkan ke sistem tinggal
 * dijumlah dan tidak dikira-kira di lapangan.
 */
class LembarHitung extends Page
{
    /** Baris kosong tiap rak, untuk bahan yang ketemu tapi belum terdaftar. */
    public const BARIS_KOSONG = 2;

    /** Ditaruh paling bawah karena bukan lokasi yang bisa didatangi. */
    private const TANPA_RAK = 'Belum Tercatat di Rak';

    protected static string $resource = MaterialOpnameResource::class;

    protected string $view = 'filament.resources.material-opnames.lembar-hitung';

    public MaterialOpname $record;

    public bool $tampilkanSistem = false;

    public function mount(MaterialOpname $record): void
    {
        $this->record = $record->load(['items.material', 'items.rack.warehouse', 'warehouse']);
    }

    public function getTitle(): string
    {
        return 'Lembar Hitung Bahan '.$this->record->opname_number;
    }

    public function getSubheading(): ?string
    {
        return $this->dariMaster()
            ? 'Sesi ini belum berisi bahan — lembar diambil dari catatan stok per rak.'
            : 'Berisi '.$this->record->items->count().' baris yang sudah terdaftar di sesi ini.';
    }

    /** Sesi draft yang masih kosong tetap harus bisa dicetak, jadi jatuh ke stok rak. */
    public function dariMaster(): bool
    {
        return $this->record->items->isEmpty();
    }

    /**
     * Baris siap cetak, dikelompokkan per rak — itu urutan jalan kaki di gudang.
     *
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function kelompok(): array
    {
        $baris = $this->dariMaster()
            ? $this->dariStokRak()
            : $this->record->items
                ->filter(fn ($row) => $row->material !== null)
                ->map(fn ($row) => $this->baris($row->material, $row->rack, (float) $row->system_qty));

        $grup = $baris->sortBy('nama')->groupBy('kelompok')->sortKeys()->all();

        // Rak yang belum tercatat tidak bisa didatangi, jadi taruh paling akhir.
        if (isset($grup[self::TANPA_RAK])) {
            $ekor = $grup[self::TANPA_RAK];
            unset($grup[self::TANPA_RAK]);
            $grup[self::TANPA_RAK] = $ekor;
        }

        return $grup;
    }

    public function jumlahBaris(): int
    {
        return collect($this->kelompok())->sum(fn (Collection $baris) => $baris->count());
    }

    /**
     * Stok per rak sebagai bahan lembar, dibatasi gudang sesi bila diisi.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function dariStokRak(): Collection
    {
        $gudang = $this->record->warehouse_id;

        $stok = MaterialStock::query()
            ->with(['material', 'rack.warehouse'])
            ->when($gudang, fn ($q) => $q->whereHas('rack', fn ($r) => $r->where('warehouse_id', $gudang)))
            ->get()
            ->filter(fn (MaterialStock $s) => $s->material?->is_active)
            ->map(fn (MaterialStock $s) => $this->baris($s->material, $s->rack, (float) $s->qty));

        // Bahan aktif yang belum punya catatan rak tetap harus dihitung —
        // justru di situ selisih paling sering muncul.
        $sisa = Material::query()
            ->where('is_active', true)
            ->whereNotIn('id', $stok->pluck('id')->unique()->all())
            ->orderBy('name')
            ->get()
            ->map(fn (Material $m) => $this->baris($m, null, 0.0));

        return $stok->concat($sisa)->values();
    }

    /** @return array<string, mixed> */
    private function baris(Material $material, ?Rack $rack, float $sistem): array
    {
        return [
            'id' => $material->id,
            'kelompok' => $rack?->fullName() ?: self::TANPA_RAK,
            'sku' => $material->sku,
            'nama' => $material->name,
            'satuan_beli' => $material->unit ?: 'pcs',
            'satuan_dasar' => $material->baseUnit(),
            'konversi' => $material->conversionLabel(),
            'berdimensi' => $material->dimension_type !== 'count',
            'sistem' => $sistem,
            'sistem_label' => $material->formatBase($sistem),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cetak')
                ->label('Cetak')
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->alpineClickHandler('window.print()'),

            Action::make('stokSistem')
                ->label(fn () => $this->tampilkanSistem ? 'Sembunyikan Stok Sistem' : 'Tampilkan Stok Sistem')
                ->icon(fn () => $this->tampilkanSistem ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color('gray')
                ->action(fn () => $this->tampilkanSistem = ! $this->tampilkanSistem),

            Action::make('isiHasil')
                ->label('Isi Hasil Hitung')
                ->icon('heroicon-o-pencil-square')
                ->color('success')
                ->visible(fn () => ! $this->record->isPosted())
                ->url(fn () => MaterialOpnameResource::getUrl('edit', ['record' => $this->record])),

            Action::make('kembali')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => MaterialOpnameResource::getUrl('index')),
        ];
    }
}
