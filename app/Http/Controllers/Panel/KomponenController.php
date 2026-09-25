<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ExhaustComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Bagian knalpot di panel baru.
 *
 * Bertingkat dua: BAGIAN induk (Header, Silincer) berisi KOMPONEN di dalamnya.
 * Karena cuma dua tingkat, seluruh pohonnya muat di satu layar — tidak perlu
 * halaman terpisah untuk membuka isi tiap bagian seperti di panel lama.
 *
 * Daftar ini murni daftar. Bahan apa yang dipakai, berapa banyak, dan
 * berukuran berapa itu milik formula — berbeda tiap model motor, jadi menaruh
 * salah satunya di sini memaksa daftar komponen digandakan per model.
 */
class KomponenController extends Controller
{
    public function index(): View
    {
        return view('panel.komponen.index', [
            'bagian' => ExhaustComponent::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($q) => $q->orderBy('sort_order')->orderBy('name')])
                ->orderBy('sort_order')->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->bersihkan($request);
        $komponen = ExhaustComponent::create($data);

        return back()->with('sukses', ($komponen->parent_id ? 'Komponen' : 'Bagian')." \"{$komponen->name}\" ditambahkan.");
    }

    public function update(Request $request, ExhaustComponent $komponen): RedirectResponse
    {
        $komponen->update($this->bersihkan($request, $komponen));

        return back()->with('sukses', "\"{$komponen->name}\" disimpan.");
    }

    public function destroy(ExhaustComponent $komponen): RedirectResponse
    {
        // Bagian yang masih berisi komponen tidak boleh hilang begitu saja:
        // anak-anaknya akan ikut terhapus lewat cascade tanpa ada yang melihat
        // berapa banyak yang lenyap.
        if ($komponen->children()->exists()) {
            return back()->with('gagal', "\"{$komponen->name}\" masih berisi {$komponen->children()->count()} komponen. Kosongkan dulu.");
        }

        $nama = $komponen->name;
        $komponen->delete();

        return back()->with('sukses', "\"{$nama}\" dihapus.");
    }

    /** @return array<string, mixed> */
    private function bersihkan(Request $request, ?ExhaustComponent $abaikan = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('exhaust_components', 'code')->ignore($abaikan)],
            'parent_id' => ['nullable', 'exists:exhaust_components,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], ['name' => 'nama', 'code' => 'kode']);

        // Bagian induk tidak boleh jadi anak dari dirinya sendiri, dan pohonnya
        // memang hanya dua tingkat — induk dari induk tidak dikenal di mana pun.
        if ($abaikan && (int) ($data['parent_id'] ?? 0) === $abaikan->id) {
            $data['parent_id'] = null;
        }

        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
