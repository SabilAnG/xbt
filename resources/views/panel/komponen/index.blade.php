<x-panel.layout judul="Bagian Knalpot"
                keterangan="Daftar bagian penyusun knalpot. Bahan dan ukurannya ada di resep, bukan di sini.">

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Pohon --}}
        <div class="space-y-4 lg:col-span-2">
            @forelse ($bagian as $b)
                <section class="kartu p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="font-semibold text-slate-800">{{ $b->name }}</h2>
                            <p class="text-[0.75rem] text-slate-400">
                                {{ $b->code ?: 'tanpa kode' }} · {{ $b->children->count() }} komponen
                            </p>
                        </div>

                        <form method="POST" action="{{ route('panel.komponen.destroy', $b) }}"
                              onsubmit="return confirm('Hapus bagian ini?')">
                            @csrf @method('DELETE')
                            <button class="text-[0.75rem] text-merah-500">hapus</button>
                        </form>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        @forelse ($b->children as $k)
                            <div class="flex items-center gap-2 rounded-xl bg-lantai px-3 py-2">
                                <span class="min-w-0 flex-1 truncate text-[0.8125rem] text-slate-700">{{ $k->name }}</span>
                                <code class="shrink-0 text-[0.7rem] text-slate-400">{{ $k->code }}</code>

                                @unless ($k->is_active)
                                    <span class="shrink-0 rounded-full bg-slate-200 px-1.5 py-0.5 text-[0.625rem] text-slate-500">nonaktif</span>
                                @endunless

                                <form method="POST" action="{{ route('panel.komponen.destroy', $k) }}"
                                      onsubmit="return confirm('Hapus komponen ini?')">
                                    @csrf @method('DELETE')
                                    <button class="shrink-0 text-[0.7rem] text-merah-500">hapus</button>
                                </form>
                            </div>
                        @empty
                            <p class="py-2 text-[0.8125rem] text-slate-400">Belum ada komponen di bagian ini.</p>
                        @endforelse
                    </div>

                    {{-- Tambah komponen ke bagian ini --}}
                    <form method="POST" action="{{ route('panel.komponen.store') }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $b->id }}">
                        <input type="hidden" name="is_active" value="1">
                        <input name="name" class="isian min-w-0 flex-1 basis-40" required placeholder="Nama komponen baru">
                        <input name="code" class="isian w-28" placeholder="Kode">
                        <button class="tombol tombol-utama">Tambah</button>
                    </form>
                </section>
            @empty
                <div class="kartu px-4 py-16 text-center">
                    <p class="text-sm font-medium text-slate-600">Belum ada bagian knalpot</p>
                    <p class="mt-1 text-[0.8125rem] text-slate-400">Mulai dari yang induk: Header dan Silincer.</p>
                </div>
            @endforelse
        </div>

        {{-- Tambah bagian induk --}}
        <section class="kartu h-fit p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Tambah bagian induk</h2>
            <p class="mb-3 text-[0.8125rem] text-slate-500">
                Bagian induk adalah wadah — Header, Silincer. Komponennya ditambahkan di dalam kartunya masing-masing.
            </p>

            <form method="POST" action="{{ route('panel.komponen.store') }}" class="space-y-2">
                @csrf
                <input type="hidden" name="is_active" value="1">
                <input name="name" class="isian" required placeholder="Nama bagian" value="{{ old('name') }}">
                <input name="code" class="isian" placeholder="Kode (boleh kosong)" value="{{ old('code') }}">
                <input name="notes" class="isian" placeholder="Catatan (boleh kosong)" value="{{ old('notes') }}">
                @error('name') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                @error('code') <p class="text-[0.8125rem] text-merah-500">{{ $message }}</p> @enderror
                <button class="tombol tombol-utama w-full">Tambah bagian</button>
            </form>
        </section>
    </div>
</x-panel.layout>
