<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Machine;
use App\Models\Material;
use App\Models\OverheadItem;
use App\Models\PriceTier;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Angka dasar perhitungan HPP — padanan halaman "Pengaturan Produksi" lama. */
class ProductionSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.production-settings', [
            'values' => [
                'tarif_listrik' => Machine::tarifListrik(),
                'kerf_mm' => Material::kerf(),
                'target_produksi_bulanan' => OverheadItem::targetPerMonth(),
                'pembulatan_harga' => PriceTier::rounding(),
            ],
            'overheadPerUnit' => OverheadItem::perUnit(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tarif_listrik' => ['required', 'numeric', 'min:0'],
            'kerf_mm' => ['required', 'numeric', 'min:0'],
            'target_produksi_bulanan' => ['required', 'numeric', 'gt:0'],
            'pembulatan_harga' => ['required', 'numeric', 'min:0'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => 'produksi.'.$key], ['value' => $value, 'type' => 'text', 'group' => 'produksi']);
        }

        return back()->with('status', 'Pengaturan produksi disimpan. Semua HPP formula ikut berubah.');
    }
}
