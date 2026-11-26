<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Pengaturan produksi: lebar potong gergaji (kerf) yang dipakai saat memotong pipa. */
class ProductionSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.production-settings', ['kerfMm' => Material::kerfCm() * 10]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['kerf_mm' => ['required', 'numeric', 'min:0', 'max:20']]);

        Setting::updateOrCreate(['key' => 'produksi.kerf_mm'], ['value' => $data['kerf_mm'], 'type' => 'text', 'group' => 'produksi']);

        return back()->with('status', 'Pengaturan produksi disimpan.');
    }
}
