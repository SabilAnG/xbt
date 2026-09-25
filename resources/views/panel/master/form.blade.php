@php $baru = ! $baris->exists; @endphp

<x-panel.layout :judul="($baru ? 'Tambah ' : 'Ubah ').$spek['judul']"
                :keterangan="$baru ? $spek['petunjuk'] : $baris->name">

    <x-slot:aksi>
        <a href="{{ route('panel.master.index', $jenis) }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-master" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <form id="form-master" method="POST"
          action="{{ $baru ? route('panel.master.store', $jenis) : route('panel.master.update', [$jenis, $baris->id]) }}"
          class="kartu max-w-2xl space-y-4 p-5">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <div>
            <label for="name" class="label">{{ $spek['nama'] }}</label>
            <input id="name" name="name" class="isian" required maxlength="255"
                   value="{{ old('name', $baris->name) }}">
            @error('name') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>

        @if ($spek['slug'])
        <div>
            <label for="slug" class="label">Slug</label>
            <input id="slug" name="slug" class="isian" maxlength="255"
                   value="{{ old('slug', $baris->slug) }}" placeholder="dibuat sendiri dari namanya">
            <p class="mt-1 text-[0.75rem] text-slate-400">
                Dibiarkan kosong berarti dibuat dari namanya. Tidak ikut berubah saat nama diubah —
                mengganti slug baris yang sudah dipakai bisa memutus rujukan di tempat lain.
            </p>
            @error('slug') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
        </div>
        @endif

        @foreach ($spek['tambahan'] as $medan => $isian)
            <div>
                <label for="{{ $medan }}" class="label">
                    {{ $isian['label'] }}
                    @unless ($isian['wajib'] ?? false)
                        <span class="font-normal text-slate-400">(boleh kosong)</span>
                    @endunless
                </label>

                @if ($isian['jenis'] === 'pilihan')
                    <select id="{{ $medan }}" name="{{ $medan }}" class="isian">
                        <option value="">— belum dipilih —</option>
                        @foreach ($pilihan[$medan] ?? [] as $nilai => $label)
                            <option value="{{ $nilai }}" @selected((string) old($medan, $baris->{$medan}) === (string) $nilai)>{{ $label }}</option>
                        @endforeach
                    </select>
                @elseif ($isian['jenis'] === 'angka')
                    <div class="relative">
                        @isset($isian['awalan'])
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">{{ $isian['awalan'] }}</span>
                        @endisset
                        <input id="{{ $medan }}" name="{{ $medan }}" inputmode="decimal"
                               class="isian @isset($isian['awalan']) pl-10 @endisset @isset($isian['akhiran']) pr-10 @endisset"
                               value="{{ old($medan, $baris->{$medan}) }}">
                        @isset($isian['akhiran'])
                            <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">{{ $isian['akhiran'] }}</span>
                        @endisset
                    </div>
                @else
                    <input id="{{ $medan }}" name="{{ $medan }}" class="isian" maxlength="255"
                           value="{{ old($medan, $baris->{$medan}) }}">
                @endif

                @isset($isian['petunjuk'])
                    <p class="mt-1 text-[0.75rem] text-slate-400">{{ $isian['petunjuk'] }}</p>
                @endisset

                @error($medan) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>
        @endforeach

        @if ($spek['keterangan'])
            @php $kolomKeterangan = $spek['keterangan']; @endphp
            <div>
                <label for="{{ $kolomKeterangan }}" class="label">Keterangan <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                <input id="{{ $kolomKeterangan }}" name="{{ $kolomKeterangan }}" class="isian" maxlength="255"
                       value="{{ old($kolomKeterangan, $baris->{$kolomKeterangan}) }}">
                @error($kolomKeterangan) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>
        @endif

        @if ($spek['urutan'])
            <div>
                <label for="sort_order" class="label">Urutan tampil</label>
                <input id="sort_order" name="sort_order" inputmode="numeric" class="isian"
                       value="{{ old('sort_order', $baris->sort_order ?? 0) }}">
                <p class="mt-1 text-[0.75rem] text-slate-400">Angka kecil tampil lebih dulu.</p>
            </div>
        @endif

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $baris->exists ? $baris->is_active : true))
                   class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
            Aktif
        </label>
    </form>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.master.destroy', [$jenis, $baris->id]) }}"
              class="mt-4 max-w-2xl"
              onsubmit="return confirm('Nonaktifkan baris ini? Datanya tetap ada supaya nota lama tidak kehilangan rujukan.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)] hover:text-merah-400">
                Nonaktifkan
            </button>
        </form>
    @endunless
</x-panel.layout>
