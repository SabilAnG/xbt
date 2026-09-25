<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ExhaustComponent;
use App\Models\Formula;
use App\Models\FormulaLine;
use App\Models\FormulaService;
use App\Models\MotorcycleModel;
use App\Models\ProductionItem;
use App\Models\ProductionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Resep knalpot (formula) di panel baru.
 *
 * Resep satu knalpot untuk satu type motor: komponennya apa, bahannya apa,
 * ukurannya berapa. Dari sinilah modal per knalpot dihitung.
 *
 * `input_mode` tidak pernah diketik orang — bentuk bahan di master sudah
 * menjawabnya. Pipa selalu dipotong sepanjang sekian, plat selalu sekian kali
 * sekian, barang beli jadi selalu dihitung per buah. Jadi mode diturunkan dari
 * bahannya saat baris dibuat, sama seperti di panel lama.
 */
class ResepController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));

        return view('panel.resep.index', [
            'resep' => Formula::query()
                ->with('motorcycleModel')
                ->withCount('lines')
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$cari}%")->orWhere('code', 'like', "%{$cari}%")
                ))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'cari' => $cari,
        ]);
    }

    public function show(Formula $resep): View
    {
        $resep->load(['lines.item', 'lines.component', 'services.service', 'motorcycleModel']);

        return view('panel.resep.show', [
            'resep' => $resep,
            'daftarBahan' => ProductionItem::where('is_active', true)->orderBy('name')->get(),
            'daftarBagian' => ExhaustComponent::orderBy('name')->pluck('name', 'id'),
            'daftarJasa' => ProductionService::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'daftarMotor' => MotorcycleModel::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Formula $resep): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('formulas', 'code')->ignore($resep)],
            'motorcycle_model_id' => ['nullable', 'exists:motorcycle_models,id'],
            'output_qty' => ['required', 'numeric', 'min:0.001'],
            'output_unit' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string'],
        ], [], ['name' => 'nama', 'output_qty' => 'jumlah hasil']);

        $data['is_active'] = $request->boolean('is_active');
        $resep->update($data);

        return back()->with('sukses', 'Resep disimpan.');
    }

    /**
     * Tambah satu baris bahan.
     *
     * Mode ukurannya diturunkan dari BENTUK bahannya, bukan ditanyakan: pipa
     * dipotong sepanjang sekian, plat sekian kali sekian, sisanya per buah.
     */
    public function tambahBaris(Request $request, Formula $resep): RedirectResponse
    {
        $data = $request->validate([
            'production_item_id' => ['required', 'exists:production_items,id'],
            'exhaust_component_id' => ['nullable', 'exists:exhaust_components,id'],
            'piece_count' => ['required', 'numeric', 'min:0.001'],
            'piece_length_mm' => ['nullable', 'numeric', 'min:0'],
            'piece_width_mm' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [], ['production_item_id' => 'bahan', 'piece_count' => 'jumlah']);

        $bahan = ProductionItem::findOrFail($data['production_item_id']);

        $mode = match ($bahan->shape) {
            'linear' => 'length',
            'sheet' => 'rect',
            default => 'count',
        };

        if ($mode === 'length' && blank($data['piece_length_mm'])) {
            return back()->with('gagal', 'Bahan batangan perlu panjang potongannya.');
        }

        if ($mode === 'rect' && (blank($data['piece_length_mm']) || blank($data['piece_width_mm']))) {
            return back()->with('gagal', 'Bahan lembaran perlu panjang dan lebar potongannya.');
        }

        $resep->lines()->create($data + [
            'input_mode' => $mode,
            'size_unit' => 'mm',
            'sort_order' => (int) $resep->lines()->max('sort_order') + 1,
        ]);

        return back()->with('sukses', "Bahan \"{$bahan->name}\" ditambahkan ke resep.");
    }

    public function hapusBaris(Formula $resep, FormulaLine $baris): RedirectResponse
    {
        abort_unless($baris->formula_id === $resep->id, 404);

        $baris->delete();

        return back()->with('sukses', 'Baris bahan dihapus.');
    }

    public function tambahJasa(Request $request, Formula $resep): RedirectResponse
    {
        $data = $request->validate([
            'production_service_id' => ['required', 'exists:production_services,id'],
            'qty' => ['required', 'numeric', 'min:0.001'],
        ], [], ['production_service_id' => 'jasa', 'qty' => 'jumlah']);

        $resep->services()->create($data + [
            'sort_order' => (int) $resep->services()->max('sort_order') + 1,
        ]);

        return back()->with('sukses', 'Jasa ditambahkan ke resep.');
    }

    public function hapusJasa(Formula $resep, FormulaService $jasa): RedirectResponse
    {
        abort_unless($jasa->formula_id === $resep->id, 404);

        $jasa->delete();

        return back()->with('sukses', 'Jasa dihapus dari resep.');
    }
}
