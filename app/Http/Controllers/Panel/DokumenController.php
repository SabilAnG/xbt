<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\PostingService;
use App\Support\DaftarDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Satu controller untuk empat nota operasional.
 *
 * Bentuknya dideklarasikan di DaftarDokumen. Pembukuan diserahkan sepenuhnya
 * ke PostingService — panel ini hanya memanggil, tidak pernah menghitung stok
 * atau kas sendiri.
 */
class DokumenController extends Controller
{
    public function __construct(private readonly PostingService $posting) {}

    public function index(Request $request, string $jenis): View
    {
        $spek = $this->spek($jenis);
        $cari = trim((string) $request->query('cari', ''));
        $status = in_array($request->query('status'), ['draft', 'posted'], true) ? $request->query('status') : null;

        $nota = $spek['model']::query()
            ->when($spek['baris'], fn ($q) => $q->withCount('items'))
            ->when($cari !== '', fn ($q) => $q->where(
                fn ($q) => $q->where($spek['nomor'], 'like', "%{$cari}%")
                    ->orWhere($spek['pihak']['kolom'], 'like', "%{$cari}%")
            ))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc($spek['tanggal'])
            ->paginate(25)
            ->withQueryString();

        return view('panel.dokumen.index', compact('spek', 'jenis', 'nota', 'cari', 'status'));
    }

    public function show(string $jenis, int $id): View
    {
        $spek = $this->spek($jenis);

        $nota = $spek['model']::query()
            ->when($spek['baris'], fn ($q) => $q->with('items.item'))
            ->findOrFail($id);

        return view('panel.dokumen.show', compact('spek', 'jenis', 'nota'));
    }

    public function bukukan(string $jenis, int $id): RedirectResponse
    {
        $spek = $this->spek($jenis);
        $nota = $spek['model']::findOrFail($id);

        // Dijaga walau tombolnya hanya muncul untuk draft: alamat POST tetap
        // bisa dikirim langsung, dan membukukan dua kali berarti stok bergerak
        // dua kali tanpa ada yang menyadarinya.
        if ($nota->status === 'posted') {
            return back()->with('gagal', 'Nota ini sudah dibukukan.');
        }

        $this->posting->{$spek['post']}($nota);

        return back()->with('sukses', "{$spek['judul']} {$nota->{$spek['nomor']}} dibukukan.");
    }

    public function batalkan(string $jenis, int $id): RedirectResponse
    {
        $spek = $this->spek($jenis);

        if ($spek['unpost'] === null) {
            abort(404);
        }

        $nota = $spek['model']::findOrFail($id);

        if ($nota->status !== 'posted') {
            return back()->with('gagal', 'Nota ini masih draft, tidak ada yang perlu dibatalkan.');
        }

        $this->posting->{$spek['unpost']}($nota);

        return back()->with('sukses', "Pembukuan {$nota->{$spek['nomor']}} dibatalkan.");
    }

    /** @return array<string, mixed> */
    private function spek(string $jenis): array
    {
        return DaftarDokumen::cari($jenis) ?? abort(404);
    }
}
