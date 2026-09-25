<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Iklan di situs publik.
 *
 * Masa tayang ditentukan `starts_at` dan `ends_at`; iklan yang sudah lewat
 * tidak perlu dimatikan manual. Karena itu daftar ini menandai sendiri mana
 * yang sedang tayang, mana yang menunggu, dan mana yang sudah habis — status
 * `is_active` saja tidak menjawab pertanyaan itu.
 */
class IklanController extends Controller
{
    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));

        return view('panel.iklan.index', [
            'iklan' => Advertisement::query()
                ->when($cari !== '', fn ($q) => $q->where(
                    fn ($q) => $q->where('title', 'like', "%{$cari}%")
                        ->orWhere('advertiser_name', 'like', "%{$cari}%")
                ))
                ->orderBy('sort_order')->orderByDesc('starts_at')
                ->paginate(20)
                ->withQueryString(),
            'cari' => $cari,
        ]);
    }

    public function create(): View
    {
        return view('panel.iklan.form', ['iklan' => new Advertisement]);
    }

    public function edit(Advertisement $iklan): View
    {
        return view('panel.iklan.form', ['iklan' => $iklan]);
    }

    public function store(Request $request): RedirectResponse
    {
        $iklan = Advertisement::create($this->bersihkan($request));

        return redirect()->route('panel.iklan.index')
            ->with('sukses', "Iklan \"{$iklan->title}\" ditambahkan.");
    }

    public function update(Request $request, Advertisement $iklan): RedirectResponse
    {
        $iklan->update($this->bersihkan($request, $iklan));

        return redirect()->route('panel.iklan.index')
            ->with('sukses', "Iklan \"{$iklan->title}\" disimpan.");
    }

    public function destroy(Advertisement $iklan): RedirectResponse
    {
        $judul = $iklan->title;

        if ($iklan->image_path) {
            Storage::disk('site')->delete($iklan->image_path);
        }

        $iklan->delete();

        return redirect()->route('panel.iklan.index')
            ->with('sukses', "Iklan \"{$judul}\" dihapus.");
    }

    // ------------------------------------------------------------- internal

    /** @return array<string, mixed> */
    private function bersihkan(Request $request, ?Advertisement $iklan = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'position' => ['required', Rule::in(array_keys(Advertisement::POSITIONS))],
            'target_url' => ['nullable', 'url', 'max:2048'],
            'advertiser_name' => ['nullable', 'string', 'max:255'],
            'advertiser_contact' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'gambar' => [$iklan === null ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'ends_at.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ], [
            'title' => 'judul',
            'position' => 'posisi',
            'target_url' => 'alamat tujuan',
            'gambar' => 'gambar',
        ]);

        if ($berkas = $request->file('gambar')) {
            // Gambar lama dibuang saat diganti; kalau tidak, tiap penggantian
            // meninggalkan satu berkas yatim yang tidak pernah dibersihkan.
            if ($iklan?->image_path) {
                Storage::disk('site')->delete($iklan->image_path);
            }

            $data['image_path'] = $berkas->storeAs(
                'storage/iklan',
                Str::ulid().'.'.$berkas->extension(),
                'site',
            );
        }

        unset($data['gambar']);

        $data['sort_order'] ??= 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
