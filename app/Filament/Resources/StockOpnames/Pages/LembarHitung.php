<?php

namespace App\Filament\Resources\StockOpnames\Pages;

use App\Filament\Resources\StockOpnames\StockOpnameResource;
use App\Models\Item;
use App\Models\StockOpname;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Lembar hitung fisik — kertas yang dibawa ke gudang.
 *
 * Kolom "Hitung Fisik" sengaja dibiarkan kosong: petugas menulis angkanya di
 * depan rak, hasilnya baru dimasukkan ke form opname. Stok sistem bawaannya
 * disembunyikan supaya angka sistem tidak sekadar disalin — tombol di header
 * memunculkannya bila lembarnya dipakai untuk mencocokkan, bukan menghitung.
 */
class LembarHitung extends Page
{
    /** Baris kosong tiap kelompok, untuk barang yang ketemu di rak tapi belum terdaftar. */
    public const BARIS_KOSONG = 2;

    protected static string $resource = StockOpnameResource::class;

    protected string $view = 'filament.resources.stock-opnames.lembar-hitung';

    public StockOpname $record;

    public bool $tampilkanSistem = false;

    public function mount(StockOpname $record): void
    {
        $this->record = $record->load(['items.item.category']);
    }

    public function getTitle(): string
    {
        return 'Lembar Hitung '.$this->record->opname_number;
    }

    public function getSubheading(): ?string
    {
        return $this->dariMaster()
            ? 'Sesi ini belum berisi barang — lembar diambil dari seluruh barang aktif.'
            : 'Berisi '.$this->record->items->count().' barang yang sudah terdaftar di sesi ini.';
    }

    /** Sesi draft yang masih kosong tetap harus bisa dicetak, jadi jatuh ke master barang. */
    public function dariMaster(): bool
    {
        return $this->record->items->isEmpty();
    }

    /**
     * Baris siap cetak, dikelompokkan per kategori supaya urutan jalan di
     * gudang tidak lompat-lompat antar rak.
     *
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function kelompok(): array
    {
        $baris = $this->dariMaster()
            ? Item::query()->with('category')->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Item $item) => $this->baris($item, (float) $item->stock))
            : $this->record->items
                ->filter(fn ($row) => $row->item !== null)
                ->map(fn ($row) => $this->baris($row->item, (float) $row->system_qty))
                ->sortBy('nama')
                ->values();

        return $baris->groupBy('kelompok')->sortKeys()->all();
    }

    public function jumlahBaris(): int
    {
        return collect($this->kelompok())->sum(fn (Collection $baris) => $baris->count());
    }

    /** @return array<string, mixed> */
    private function baris(Item $item, float $sistem): array
    {
        return [
            'kelompok' => $item->category?->name ?: 'Tanpa Kategori',
            'sku' => $item->sku,
            'nama' => $item->name,
            'satuan' => $item->unit ?: 'pcs',
            'sistem' => $sistem,
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
                ->url(fn () => StockOpnameResource::getUrl('edit', ['record' => $this->record])),

            Action::make('kembali')
                ->label('Kembali')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->url(fn () => StockOpnameResource::getUrl('index')),
        ];
    }
}
