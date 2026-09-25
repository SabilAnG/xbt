<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PartnerProvisioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Partner (toko yang menumpang sistem ini) di panel baru.
 *
 * Seluruh tindakan berat — menyetujui, menolak, memperpanjang, menghapus —
 * diserahkan ke PartnerProvisioning. Menyetujui berarti MEMBUAT DATABASE baru
 * dan menjalankan seluruh migrasi di sana; menghapus berarti menjatuhkannya.
 * Tidak ada satu pun dari itu yang ditulis ulang di sini.
 */
class PartnerController extends Controller
{
    public function __construct(private readonly PartnerProvisioning $siapkan) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $status = in_array($request->query('status'), array_keys(Tenant::STATUSES), true)
            ? $request->query('status')
            : null;

        return view('panel.partner.index', [
            'partner' => Tenant::query()
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('name', 'like', "%{$cari}%")
                        ->orWhere('id', 'like', "%{$cari}%")
                        ->orWhere('owner_phone', 'like', "%{$cari}%")
                ))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'cari' => $cari,
            'status' => $status,
            'menunggu' => Tenant::menunggu()->count(),
        ]);
    }

    public function show(Tenant $partner): View
    {
        return view('panel.partner.show', ['partner' => $partner]);
    }

    /**
     * Setujui partner: database miliknya dibuat, migrasi dijalankan, data awal
     * diisi. Tindakan paling berat di seluruh panel — dan tidak bisa diulang,
     * jadi status diperiksa dulu.
     */
    public function setujui(Request $request, Tenant $partner): RedirectResponse
    {
        if ($partner->status === Tenant::AKTIF) {
            return back()->with('gagal', 'Partner ini sudah aktif.');
        }

        $data = $request->validate([
            'fitur' => ['required', 'array', 'min:1'],
            'fitur.*' => [Rule::in(array_keys(Tenant::FEATURES))],
            'hari' => ['required', Rule::in(array_keys(Tenant::DURATIONS))],
        ], [
            'fitur.required' => 'Pilih minimal satu menu yang boleh dibuka partner.',
        ]);

        $this->siapkan->setujui($partner, $data['fitur'], (int) $data['hari']);

        return back()->with('sukses', "Partner {$partner->name} disetujui. Databasenya sudah disiapkan.");
    }

    public function perpanjang(Request $request, Tenant $partner): RedirectResponse
    {
        $data = $request->validate([
            'hari' => ['required', Rule::in(array_keys(Tenant::DURATIONS))],
        ]);

        $this->siapkan->perpanjang($partner, (int) $data['hari']);

        return back()->with('sukses', "Masa pakai {$partner->name} diperpanjang.");
    }

    /** Ubah hak menu tanpa menyentuh database partnernya. */
    public function hak(Request $request, Tenant $partner): RedirectResponse
    {
        $data = $request->validate([
            'fitur' => ['array'],
            'fitur.*' => [Rule::in(array_keys(Tenant::FEATURES))],
        ]);

        $partner->forceFill(['features' => array_values($data['fitur'] ?? [])])->save();

        return back()->with('sukses', 'Hak menu diperbarui.');
    }

    public function tolak(Request $request, Tenant $partner): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:255']]);

        $this->siapkan->tolak($partner, $data['alasan'] ?? null);

        return back()->with('sukses', "Pendaftaran {$partner->name} ditolak.");
    }

    /**
     * Hapus partner beserta databasenya.
     *
     * Nama toko diminta diketik ulang sebagai pengaman: ini menjatuhkan
     * seluruh database partner, dan tidak ada tombol urungkan di mana pun.
     */
    public function destroy(Request $request, Tenant $partner): RedirectResponse
    {
        $request->validate(['konfirmasi' => ['required', 'string']]);

        if (trim((string) $request->input('konfirmasi')) !== $partner->name) {
            return back()->with('gagal', 'Nama toko tidak cocok. Databasenya TIDAK dihapus.');
        }

        $nama = $partner->name;
        $this->siapkan->hapus($partner);

        return redirect()->route('panel.partner.index')
            ->with('sukses', "Partner {$nama} dan databasenya dihapus.");
    }
}
