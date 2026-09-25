@php $baru = ! $produk->exists; @endphp

<x-panel.layout :judul="$baru ? 'Tambah Produk' : 'Ubah Produk'" :keterangan="$baru ? null : $produk->name">

    <x-slot:aksi>
        <a href="{{ route('panel.katalog.index') }}" class="tombol tombol-hening">Batal</a>
        <button type="submit" form="form-produk" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    <div class="grid gap-4 lg:grid-cols-3">
        <form id="form-produk" method="POST" class="space-y-4 lg:col-span-2"
              action="{{ $baru ? route('panel.katalog.store') : route('panel.katalog.update', $produk) }}">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <section class="kartu space-y-4 p-5">
                <div>
                    <label for="name" class="label">Nama Produk</label>
                    <input id="name" name="name" class="isian" required maxlength="255" value="{{ old('name', $produk->name) }}">
                    @error('name') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="slug" class="label">Slug</label>
                        <input id="slug" name="slug" class="isian" maxlength="255"
                               value="{{ old('slug', $produk->slug) }}" placeholder="dibuat dari namanya">
                        @error('slug') <p class="mt-1 text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="price" class="label">Harga</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">Rp</span>
                            <input id="price" name="price" inputmode="decimal" class="isian pl-10"
                                   value="{{ old('price', $produk->price ? (float) $produk->price : null) }}">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="fitment" class="label">Kecocokan motor</label>
                    <input id="fitment" name="fitment" class="isian" maxlength="255"
                           value="{{ old('fitment', $produk->fitment) }}" placeholder="Contoh: Vario 125/150, Beat, Scoopy">
                </div>

                <div>
                    <label for="description" class="label">Deskripsi</label>
                    <textarea id="description" name="description" rows="8" class="isian">{{ old('description', $produk->description) }}</textarea>
                    <p class="mt-1 text-[0.75rem] text-slate-400">
                        HTML di sini ikut tampil apa adanya di halaman produk.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="sort_order" class="label">Urutan tampil</label>
                        <input id="sort_order" name="sort_order" inputmode="numeric" class="isian"
                               value="{{ old('sort_order', $produk->sort_order ?? 0) }}">
                    </div>

                    <label class="flex items-end gap-2 pb-2.5 text-sm text-slate-700">
                        <input type="checkbox" name="is_active" value="1"
                               @checked(old('is_active', $produk->exists ? $produk->is_active : true))
                               class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
                        Tampil di website
                    </label>
                </div>
            </section>
        </form>

        {{-- Galeri: form terpisah supaya unggah tidak ikut menyimpan isian di kiri
             yang mungkin belum selesai diketik. --}}
        <section class="kartu h-fit p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Galeri</h2>

            @if ($baru)
                <p class="text-[0.8125rem] text-slate-500">Simpan produknya dulu, lalu gambarnya bisa diunggah di sini.</p>
            @else
                <p class="mb-4 text-[0.8125rem] text-slate-500">Gambar pertama dipakai sebagai sampul.</p>

                <form method="POST" action="{{ route('panel.katalog.unggah', $produk) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="gambar[]" accept="image/*" multiple required
                           class="w-full rounded-xl bg-lantai px-3 py-2 text-[0.8125rem] text-slate-600 shadow-[var(--shadow-cekung)] file:mr-3 file:rounded-full file:border-0 file:bg-ungu-500 file:px-3 file:py-1.5 file:text-[0.75rem] file:font-semibold file:text-white">
                    @error('gambar.*') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                    <button class="tombol tombol-utama w-full">Unggah</button>
                </form>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    @forelse ($produk->images->sortBy('sort_order') as $gambar)
                        <div class="group relative overflow-hidden rounded-xl bg-lantai">
                            <img src="{{ asset($gambar->path) }}" alt="" class="aspect-square w-full object-cover" loading="lazy">

                            <form method="POST" action="{{ route('panel.katalog.hapus-gambar', [$produk, $gambar]) }}"
                                  class="absolute right-1 top-1" onsubmit="return confirm('Hapus gambar ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="grid h-6 w-6 place-items-center rounded-full bg-white/90 text-merah-500 opacity-0 shadow transition group-hover:opacity-100">×</button>
                            </form>
                        </div>
                    @empty
                        <p class="col-span-3 py-6 text-center text-[0.8125rem] text-slate-400">Belum ada gambar.</p>
                    @endforelse
                </div>
            @endif
        </section>
    </div>

    @unless ($baru)
        <form method="POST" action="{{ route('panel.katalog.destroy', $produk) }}" class="mt-4"
              onsubmit="return confirm('Hapus produk ini beserta seluruh gambarnya?')">
            @csrf
            @method('DELETE')
            <button class="tombol bg-white text-merah-500 shadow-[var(--shadow-timbul-kecil)]">Hapus produk</button>
        </form>
    @endunless
</x-panel.layout>
