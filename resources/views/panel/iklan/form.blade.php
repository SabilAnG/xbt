@php $baru = ! $iklan->exists; @endphp

<x-panel.layout :judul="$baru ? 'Tambah Iklan' : 'Ubah Iklan'" :keterangan="$baru ? null : $iklan->title">

    <x-slot:aksi>
        <a href="{{ route('panel.iklan.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-iklan" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <form id="form-iklan" method="POST" enctype="multipart/form-data" class="max-w-3xl space-y-4"
          action="{{ $baru ? route('panel.iklan.store') : route('panel.iklan.update', $iklan) }}">
        @csrf
        @unless ($baru) @method('PUT') @endunless

        <section class="kartu grid gap-4 p-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="label">Judul</label>
                <input id="title" name="title" class="isian" required maxlength="255" value="{{ old('title', $iklan->title) }}">
                @error('title') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="position" class="label">Posisi di situs</label>
                <select id="position" name="position" class="isian" required>
                    @foreach (App\Models\Advertisement::POSITIONS as $nilai => $label)
                        <option value="{{ $nilai }}" @selected(old('position', $iklan->position) === $nilai)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:col-span-2">
                <label for="gambar" class="label">
                    Gambar {{ $baru ? '' : '(kosongkan bila tidak diganti)' }}
                </label>

                @if ($iklan->image_path)
                    <img src="{{ asset($iklan->image_path) }}" alt="" class="mb-2 w-full rounded-xl object-cover shadow-[var(--shadow-timbul-kecil)]">
                @endif

                <input id="gambar" type="file" name="gambar" accept="image/*" @required($baru)
                       class="w-full rounded-xl bg-lantai px-3 py-2 text-[0.8125rem] text-slate-600 shadow-[var(--shadow-cekung)] file:mr-3 file:rounded-full file:border-0 file:bg-ungu-500 file:px-3 file:py-1.5 file:text-[0.75rem] file:font-semibold file:text-white">
                @error('gambar') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="target_url" class="label">Alamat tujuan <span class="font-normal text-slate-400">(boleh kosong)</span></label>
                <input id="target_url" name="target_url" type="url" class="isian" maxlength="2048"
                       value="{{ old('target_url', $iklan->target_url) }}" placeholder="https://…">
                @error('target_url') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
            </div>

            @foreach (['starts_at' => 'Mulai tayang', 'ends_at' => 'Berakhir'] as $medan => $label)
                <div>
                    <label for="{{ $medan }}" class="label">{{ $label }}</label>
                    <input id="{{ $medan }}" name="{{ $medan }}" type="date" class="isian"
                           value="{{ old($medan, $iklan->{$medan}?->format('Y-m-d')) }}">
                    @error($medan) <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div>
                <label for="advertiser_name" class="label">Nama pemasang</label>
                <input id="advertiser_name" name="advertiser_name" class="isian" maxlength="255"
                       value="{{ old('advertiser_name', $iklan->advertiser_name) }}">
            </div>

            <div>
                <label for="advertiser_contact" class="label">Kontak pemasang</label>
                <input id="advertiser_contact" name="advertiser_contact" class="isian" maxlength="255"
                       value="{{ old('advertiser_contact', $iklan->advertiser_contact) }}">
            </div>

            <div>
                <label for="sort_order" class="label">Urutan tampil</label>
                <input id="sort_order" name="sort_order" inputmode="numeric" class="isian"
                       value="{{ old('sort_order', $iklan->sort_order ?? 0) }}">
            </div>

            <label class="flex items-end gap-2 pb-2.5 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1"
                       @checked(old('is_active', $iklan->exists ? $iklan->is_active : true))
                       class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
                Aktif
            </label>

            <div class="sm:col-span-2">
                <label for="notes" class="label">Catatan</label>
                <textarea id="notes" name="notes" rows="2" class="isian">{{ old('notes', $iklan->notes) }}</textarea>
            </div>
        </section>
    </form>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.iklan.destroy', $iklan) }}" class="mt-4 max-w-3xl"
              onsubmit="return confirm('Hapus iklan ini beserta gambarnya?')">
            @csrf
            @method('DELETE')
            <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)]">Hapus iklan</button>
        </form>
    @endunless
</x-panel.layout>
