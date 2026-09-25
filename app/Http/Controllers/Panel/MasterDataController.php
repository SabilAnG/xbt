<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Support\DaftarMasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Satu controller untuk delapan layar master data.
 *
 * Bentuknya dideklarasikan di DaftarMasterData, bukan di sini — jadi menambah
 * layar master data baru cukup satu entri di daftar itu, tanpa controller dan
 * tanpa view baru.
 */
class MasterDataController extends Controller
{
    public function index(Request $request, string $jenis): View
    {
        $spek = $this->spek($jenis);
        $cari = trim((string) $request->query('cari', ''));

        $baris = $spek['model']::query()
            ->when($cari !== '', fn ($q) => $q->where('name', 'like', "%{$cari}%"))
            ->orderBy($spek['urutan'] ? 'sort_order' : 'name')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('panel.master.index', compact('spek', 'jenis', 'baris', 'cari'));
    }

    public function create(string $jenis): View
    {
        $spek = $this->spek($jenis);

        return view('panel.master.form', [
            'spek' => $spek,
            'jenis' => $jenis,
            'baris' => new $spek['model'],
            'pilihan' => $this->pilihan($spek),
        ]);
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $spek = $this->spek($jenis);
        $baris = $spek['model']::create($this->bersihkan($request, $spek));

        return redirect()
            ->route('panel.master.index', $jenis)
            ->with('sukses', "\"{$baris->name}\" ditambahkan.");
    }

    public function edit(string $jenis, int $id): View
    {
        $spek = $this->spek($jenis);

        return view('panel.master.form', [
            'spek' => $spek,
            'jenis' => $jenis,
            'baris' => $spek['model']::findOrFail($id),
            'pilihan' => $this->pilihan($spek),
        ]);
    }

    public function update(Request $request, string $jenis, int $id): RedirectResponse
    {
        $spek = $this->spek($jenis);
        $baris = $spek['model']::findOrFail($id);
        $baris->update($this->bersihkan($request, $spek, $baris));

        return redirect()
            ->route('panel.master.index', $jenis)
            ->with('sukses', "\"{$baris->name}\" disimpan.");
    }

    /**
     * Baris master data hampir selalu sudah dipakai transaksi lain, dan
     * menghapusnya meninggalkan nota yang menunjuk ke ketiadaan. Yang tidak
     * terpakai lagi cukup dinonaktifkan — itu sebabnya ada kolom `is_active`.
     */
    public function destroy(string $jenis, int $id): RedirectResponse
    {
        $spek = $this->spek($jenis);
        $baris = $spek['model']::findOrFail($id);

        $baris->forceFill(['is_active' => false])->save();

        return redirect()
            ->route('panel.master.index', $jenis)
            ->with('sukses', "\"{$baris->name}\" dinonaktifkan. Datanya tetap ada supaya nota lama tidak kehilangan rujukan.");
    }

    // ------------------------------------------------------------- internal

    /** @return array<string, mixed> */
    private function spek(string $jenis): array
    {
        return DaftarMasterData::cari($jenis) ?? abort(404);
    }

    /**
     * Isi pilihan untuk isian tambahan bertipe `pilihan`.
     *
     * Yang bersumber dari model dibaca di sini sekali, bukan di dalam view —
     * view yang mengambil sendiri berarti query baru tiap kali dirender.
     *
     * @param  array<string, mixed>  $spek
     * @return array<string, array<int|string, string>>
     */
    private function pilihan(array $spek): array
    {
        $hasil = [];

        foreach ($spek['tambahan'] as $medan => $isian) {
            if (($isian['jenis'] ?? null) !== 'pilihan') {
                continue;
            }

            $hasil[$medan] = isset($isian['sumber'])
                ? $isian['sumber']::orderBy('name')->pluck('name', 'id')->all()
                : ($isian['pilihan'] ?? []);
        }

        return $hasil;
    }

    /**
     * Validasi, lalu isi slug bila dibiarkan kosong.
     *
     * Slug TIDAK ikut berubah saat nama diubah. Begitu satu baris dipakai
     * transaksi, mengganti slug-nya diam-diam bisa memutus rujukan di tempat
     * yang tidak kelihatan dari sini.
     *
     * @param  array<string, mixed>  $spek
     * @return array<string, mixed>
     */
    private function bersihkan(Request $request, array $spek, ?Model $abaikan = null): array
    {
        $tabel = (new $spek['model'])->getTable();

        $aturan = ['name' => ['required', 'string', 'max:255']];

        if ($spek['slug']) {
            $aturan['slug'] = ['nullable', 'string', 'max:255', Rule::unique($tabel, 'slug')->ignore($abaikan)];
        }

        if ($spek['keterangan']) {
            $aturan[$spek['keterangan']] = ['nullable', 'string', 'max:255'];
        }

        if ($spek['urutan']) {
            $aturan['sort_order'] = ['nullable', 'integer', 'min:0'];
        }

        foreach ($spek['tambahan'] as $medan => $isian) {
            $wajib = ($isian['wajib'] ?? false) ? 'required' : 'nullable';

            $aturan[$medan] = match ($isian['jenis']) {
                'angka' => [$wajib, 'numeric', 'min:0'],
                'pilihan' => isset($isian['sumber'])
                    ? [$wajib, Rule::exists((new $isian['sumber'])->getTable(), 'id')]
                    : [$wajib, Rule::in(array_keys($isian['pilihan'] ?? []))],
                default => [$wajib, 'string', 'max:255'],
            };
        }

        $data = $request->validate($aturan);

        if ($spek['slug']) {
            $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : Str::slug($data['name']);
        }

        $data['is_active'] = $request->boolean('is_active');

        if ($spek['urutan']) {
            $data['sort_order'] ??= 0;
        }

        return $data;
    }
}
