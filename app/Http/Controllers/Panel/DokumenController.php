<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\PostingService;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Wallet;
use App\Services\DocumentNumber;
use App\Support\DaftarDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return view('panel.dokumen.show', [
            'spek' => $spek,
            'jenis' => $jenis,
            'nota' => $nota,
            'daftarBarang' => $spek['baris']
                ? Item::where('is_active', true)->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
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

    public function create(string $jenis): View
    {
        $spek = $this->spek($jenis);

        return view('panel.dokumen.form', [
            'spek' => $spek,
            'jenis' => $jenis,
            'nomor' => DocumentNumber::next($spek['tabel']),
            'pilihan' => $this->pilihan($spek),
        ]);
    }

    /**
     * Buat nota baru sebagai DRAFT.
     *
     * Barisnya ditambahkan sesudahnya di halaman rinciannya, bukan sekaligus di
     * sini: nota tanpa baris tetap sah sebagai draft, dan menambah baris satu
     * per satu bekerja tanpa JavaScript sama sekali.
     */
    public function store(Request $request, string $jenis): RedirectResponse
    {
        $spek = $this->spek($jenis);

        $aturan = [
            $spek['nomor'] => ['required', 'string', 'max:64', Rule::unique((new $spek['model'])->getTable(), $spek['nomor'])],
            $spek['tanggal'] => ['required', 'date'],
        ];

        foreach ($spek['kepala'] as $medan => [$label, $jenisIsian]) {
            $aturan[$medan] = match ($jenisIsian) {
                'angka' => ['required', 'numeric', 'min:0'],
                'dompet' => ['nullable', 'exists:wallets,id'],
                'kategori_pengeluaran' => ['nullable', 'exists:expense_categories,id'],
                default => ['nullable', 'string', 'max:255'],
            };
        }

        $nota = $spek['model']::create($request->validate($aturan) + ['status' => 'draft']);

        return redirect()->route('panel.dokumen.show', [$jenis, $nota->id])
            ->with('sukses', 'Nota dibuat sebagai draft. Tambahkan barisnya di sini.');
    }

    public function tambahBaris(Request $request, string $jenis, int $id): RedirectResponse
    {
        $spek = $this->spek($jenis);

        if (! $spek['baris']) {
            abort(404);
        }

        $nota = $spek['model']::findOrFail($id);

        if ($nota->status === 'posted') {
            return back()->with('gagal', 'Nota yang sudah dibukukan tidak bisa diubah. Batalkan dulu pembukuannya.');
        }

        $aturan = ['item_id' => ['required', 'exists:items,id']];

        foreach ($spek['baris_isian'] as $medan => [$label, $wajib]) {
            $aturan[$medan] = [$wajib ? 'required' : 'nullable', 'numeric', 'min:0'];
        }

        $data = $request->validate($aturan);

        // Opname mencatat angka sistem SAAT DIHITUNG, bukan saat dibukukan.
        // Kalau diambil belakangan, selisihnya ikut bergeser oleh nota lain
        // yang kebetulan dibukukan di antara keduanya.
        if (isset($spek['baris']['selisih'])) {
            $data['system_qty'] = (float) Item::whereKey($data['item_id'])->value('stock');
        }

        $nota->items()->create($data);

        if (method_exists($nota, 'recalculateTotals')) {
            $nota->recalculateTotals();
        }

        return back()->with('sukses', 'Baris ditambahkan.');
    }

    public function hapusBaris(string $jenis, int $id, int $baris): RedirectResponse
    {
        $spek = $this->spek($jenis);
        $nota = $spek['model']::findOrFail($id);

        if ($nota->status === 'posted') {
            return back()->with('gagal', 'Nota yang sudah dibukukan tidak bisa diubah.');
        }

        $nota->items()->whereKey($baris)->delete();

        if (method_exists($nota, 'recalculateTotals')) {
            $nota->recalculateTotals();
        }

        return back()->with('sukses', 'Baris dihapus.');
    }

    /** @param array<string, mixed> $spek
     *  @return array<string, mixed> */
    private function pilihan(array $spek): array
    {
        return [
            'dompet' => Wallet::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'kategori_pengeluaran' => ExpenseCategory::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }
}
