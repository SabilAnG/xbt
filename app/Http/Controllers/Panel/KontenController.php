<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\ContentBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Konten halaman situs publik di panel baru.
 *
 * 158 blok teks dan gambar yang tersebar di seluruh situs. Panel lama
 * menyuntingnya satu per satu lewat modal; di sini SATU HALAMAN disunting
 * sekaligus, karena begitulah orang mengerjakannya — membuka halaman Home,
 * membaca dari atas ke bawah, memperbaiki beberapa kalimat, lalu simpan.
 *
 * Blok tidak bisa ditambah atau dihapus dari sini. Kuncinya ditulis di Blade
 * (`content('home.text.4', '...')`), jadi baris yang tidak punya pasangan di
 * template hanya jadi sampah yang tidak pernah tampil.
 */
class KontenController extends Controller
{
    public function index(Request $request): View
    {
        $halaman = array_key_exists((string) $request->query('halaman'), ContentBlock::PAGES)
            ? $request->query('halaman')
            : array_key_first(ContentBlock::PAGES);

        $cari = trim((string) $request->query('cari', ''));

        return view('panel.konten.index', [
            'halaman' => $halaman,
            'cari' => $cari,
            'blok' => ContentBlock::query()
                ->where('page', $halaman)
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('label', 'like', "%{$cari}%")
                        ->orWhere('value', 'like', "%{$cari}%")
                        ->orWhere('key', 'like', "%{$cari}%")
                ))
                ->orderBy('sort_order')->orderBy('key')
                ->get(),
            'jumlahPerHalaman' => ContentBlock::query()
                ->selectRaw('page, COUNT(*) AS jumlah')
                ->groupBy('page')
                ->pluck('jumlah', 'page'),
        ]);
    }

    /**
     * Simpan seluruh blok satu halaman sekaligus.
     *
     * Yang dikirim hanya blok yang memang ada di layar, dan tiap nilainya
     * dicocokkan dengan id-nya — jadi menyaring daftar lalu menyimpan tidak
     * akan mengosongkan blok yang sedang tidak tampil.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'halaman' => ['required', 'string'],
            'nilai' => ['array'],
            'nilai.*' => ['nullable', 'string'],
        ]);

        $berubah = 0;

        foreach ($data['nilai'] ?? [] as $id => $nilai) {
            $blok = ContentBlock::find($id);

            if (! $blok || $blok->value === $nilai) {
                continue;
            }

            // `saved()` di model membuang cache blok, jadi situs publik langsung
            // menyajikan yang baru tanpa ada yang perlu diingat di sini.
            $blok->forceFill(['value' => $nilai])->save();
            $berubah++;
        }

        return redirect()
            ->route('panel.konten.index', ['halaman' => $data['halaman']])
            ->with('sukses', $berubah === 0 ? 'Tidak ada yang berubah.' : "{$berubah} blok disimpan.");
    }
}
