@php
    $menunggu = $partner->status === App\Models\Tenant::MENUNGGU;
    $aktif = $partner->isAktif();
    $terpilih = $partner->features ?? [];
@endphp

<x-panel.layout :judul="$partner->name" :keterangan="$partner->alamat()">

    <x-slot:aksi>
        <a href="{{ route('panel.partner.index') }}" class="tombol tombol-hening">Kembali</a>
        @if ($partner->owner_phone)
            <a href="{{ $partner->tautanWhatsapp() }}" target="_blank" rel="noopener" class="tombol tombol-mint">WhatsApp</a>
        @endif
    </x-slot:aksi>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Keterangan --}}
        <section class="kartu grid gap-4 p-5 sm:grid-cols-2 lg:col-span-2">
            @foreach ([
                'Status' => $partner->displayStatus(),
                'Masa pakai' => $partner->sisaMasa(),
                'Pemilik' => $partner->owner_name ?: '—',
                'Telepon' => $partner->owner_phone ?: '—',
                'Id / database' => $partner->id,
                'Didaftarkan' => $partner->created_at?->format('d F Y') ?: '—',
            ] as $label => $isi)
                <div>
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ $isi }}</p>
                </div>
            @endforeach
        </section>

        {{-- Tindakan --}}
        <div class="space-y-4">
            @if ($menunggu)
                <section class="kartu p-5">
                    <h2 class="mb-1 text-sm font-semibold text-slate-800">Setujui pendaftaran</h2>
                    <p class="mb-3 text-[0.8125rem] text-slate-500">
                        Ini membuat database baru untuk partner dan menjalankan seluruh migrasi di sana.
                    </p>

                    <form method="POST" action="{{ route('panel.partner.setujui', $partner) }}" class="space-y-3"
                          onsubmit="return confirm('Setujui partner ini? Databasenya akan dibuat sekarang.')">
                        @csrf

                        <div class="space-y-1.5">
                            @foreach (App\Models\Tenant::FEATURES as $kunci => $label)
                                <label class="flex items-start gap-2 text-[0.8125rem] text-slate-700">
                                    <input type="checkbox" name="fitur[]" value="{{ $kunci }}" @checked(in_array($kunci, old('fitur', ['landing']), true))
                                           class="mt-0.5 rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
                                    {{ $label }}
                                </label>
                            @endforeach
                            @error('fitur') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                        </div>

                        <select name="hari" class="isian" required>
                            @foreach (App\Models\Tenant::DURATIONS as $hari => $label)
                                <option value="{{ $hari }}" @selected($hari === 30)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <button class="tombol tombol-utama w-full">Setujui</button>
                    </form>
                </section>

                <section class="kartu p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-800">Tolak</h2>
                    <form method="POST" action="{{ route('panel.partner.tolak', $partner) }}" class="space-y-2"
                          onsubmit="return confirm('Tolak pendaftaran ini?')">
                        @csrf
                        <input name="alasan" class="isian" maxlength="255" placeholder="Alasan (boleh kosong)">
                        <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)] w-full">Tolak</button>
                    </form>
                </section>
            @endif

            @if ($aktif || $partner->kedaluwarsa())
                <section class="kartu p-5">
                    <h2 class="mb-3 text-sm font-semibold text-slate-800">Perpanjang masa pakai</h2>
                    <form method="POST" action="{{ route('panel.partner.perpanjang', $partner) }}" class="space-y-2">
                        @csrf
                        <select name="hari" class="isian" required>
                            @foreach (App\Models\Tenant::DURATIONS as $hari => $label)
                                <option value="{{ $hari }}" @selected($hari === 30)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="tombol tombol-utama w-full">Perpanjang</button>
                    </form>
                </section>

                <section class="kartu p-5">
                    <h2 class="mb-1 text-sm font-semibold text-slate-800">Hak menu</h2>
                    <p class="mb-3 text-[0.8125rem] text-slate-500">Menentukan menu mana yang muncul di panel partner.</p>

                    <form method="POST" action="{{ route('panel.partner.hak', $partner) }}" class="space-y-3">
                        @csrf
                        <div class="space-y-1.5">
                            @foreach (App\Models\Tenant::FEATURES as $kunci => $label)
                                <label class="flex items-start gap-2 text-[0.8125rem] text-slate-700">
                                    <input type="checkbox" name="fitur[]" value="{{ $kunci }}" @checked(in_array($kunci, $terpilih, true))
                                           class="mt-0.5 rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        <button class="tombol tombol-hening w-full">Simpan hak</button>
                    </form>
                </section>
            @endif
        </div>
    </div>

    {{-- Hapus --}}
    <section class="kartu mt-4 border-l-4 border-merah-500 p-5">
        <h2 class="text-sm font-semibold text-slate-800">Hapus partner</h2>
        <p class="mt-1 text-[0.8125rem] text-slate-500">
            Ini menjatuhkan <strong>seluruh database</strong> partner — produk, nota, dan stoknya ikut hilang.
            Tidak ada tombol urungkan. Ketik nama tokonya untuk melanjutkan.
        </p>

        <form method="POST" action="{{ route('panel.partner.destroy', $partner) }}" class="mt-3 flex flex-wrap gap-2">
            @csrf
            @method('DELETE')
            <input name="konfirmasi" class="isian max-w-xs" required placeholder="{{ $partner->name }}">
            <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)]">Hapus permanen</button>
        </form>
    </section>
</x-panel.layout>
