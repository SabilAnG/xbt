<?php

namespace Tests\Feature;

use App\Filament\Pages\KalkulatorHpp;
use App\Filament\Resources\ExhaustComponents\ExhaustComponentResource;
use App\Filament\Resources\Formulas\FormulaResource;
use App\Filament\Resources\ProductionItemCategories\ProductionItemCategoryResource;
use App\Filament\Resources\ProductionItemOpnames\ProductionItemOpnameResource;
use App\Filament\Resources\ProductionItems\ProductionItemResource;
use App\Filament\Resources\ProductionPurchases\ProductionPurchaseResource;
use App\Filament\Resources\ProductionServices\ProductionServiceResource;
use App\Filament\Resources\Warehouses\WarehouseResource;
use Tests\TestCase;

/**
 * Susunan menu produksi.
 *
 * Diuji karena urutannya keputusan, bukan kebetulan. Menu produksi pernah jadi
 * satu daftar datar berisi sembilan baris, dan di situ yang dibuka tiap hari
 * tenggelam di antara yang diisi sekali lalu ditinggal. Susunan seperti itu
 * gampang kembali sendiri: satu menu baru dengan urutan asal sudah cukup.
 */
class TataMenuProduksiTest extends TestCase
{
    /** Urut seperti pekerjaannya berjalan: punya bahan, beli, hitung, resep, modal. */
    private const ALUR_HARIAN = [
        ProductionItemResource::class,
        ProductionPurchaseResource::class,
        ProductionItemOpnameResource::class,
        FormulaResource::class,
        KalkulatorHpp::class,
    ];

    /** Diisi sekali saat menyiapkan bengkel, lalu nyaris tidak disentuh lagi. */
    private const SEKALI_SETUP = [
        ProductionItemCategoryResource::class,
        WarehouseResource::class,
        ExhaustComponentResource::class,
        ProductionServiceResource::class,
    ];

    public function test_menu_harian_urut_seperti_alur_kerjanya(): void
    {
        $urutan = [];

        foreach (self::ALUR_HARIAN as $menu) {
            $this->assertSame('Produksi', $menu::getNavigationGroup(), $menu.' seharusnya menu harian.');
            $urutan[$menu] = $menu::getNavigationSort();
        }

        $menaik = $urutan;
        asort($menaik);

        $this->assertSame(
            array_keys($urutan),
            array_keys($menaik),
            'Urutan menu Produksi tidak lagi mengikuti alur kerja.'
        );
    }

    /** Dua urutan yang sama membuat posisinya ditentukan abjad, bukan alur. */
    public function test_tidak_ada_dua_menu_harian_berurutan_sama(): void
    {
        $urutan = array_map(fn (string $menu) => $menu::getNavigationSort(), self::ALUR_HARIAN);

        $this->assertSame($urutan, array_values(array_unique($urutan)));
    }

    public function test_yang_diisi_sekali_tidak_menumpuk_di_menu_harian(): void
    {
        foreach (self::SEKALI_SETUP as $menu) {
            $this->assertSame(
                'Pengaturan Produksi',
                $menu::getNavigationGroup(),
                $menu.' diisi sekali saat setup, jadi tidak ikut daftar harian.'
            );
        }
    }

    /**
     * Istilah bengkel, bukan istilah pembukuan. "Opname" dan "HPP" adalah dua
     * kata yang paling sering membuat orang berhenti dan bertanya.
     */
    public function test_menu_memakai_bahasa_sehari_hari(): void
    {
        $this->assertSame('Hitung Ulang Stok', ProductionItemOpnameResource::getNavigationLabel());
        $this->assertSame('Hitung Modal', KalkulatorHpp::getNavigationLabel());
        $this->assertSame('Resep Knalpot', FormulaResource::getNavigationLabel());
        $this->assertSame('Bagian Knalpot', ExhaustComponentResource::getNavigationLabel());
    }
}
