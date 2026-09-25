<x-panel.layout judul="Konten Halaman"
                keterangan="Teks dan gambar yang tampil di situs publik. Satu halaman disunting sekaligus.">

    <x-slot:aksi>
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="tombol tombol-hening">Lihat situs</a>
        <button type="submit" form="form-konten" class="tombol tombol-utama">Simpan halaman ini</button>
    </x-slot:aksi>

    {{-- Pilih halaman --}}
    <div class="kartu mb-4 p-3">
        <div class="flex flex-wrap gap-2">
            @foreach (App\Models\ContentBlock::PAGES as $kunci => $label)
                <a href="{{ route('panel.konten.index', ['halaman' => $kunci]) }}"
                   @class([
                       'rounded-full px-3.5 py-1.5 text-[0.8125rem] font-semibold transition',
                       'bg-ungu-500 text-white shadow-[0_8px_18px_-8px_var(--color-ungu-500)]' => $halaman === $kunci,
                       'bg-white text-slate-600 shadow-[var(--shadow-timbul-kecil)] hover:text-ungu-600' => $halaman !== $kunci,
                   ])>
                    {{ $label }}
                    <span @class(['ml-1 text-[0.7rem]', 'text-white/70' => $halaman === $kunci, 'text-slate-400' => $halaman !== $kunci])>
                        {{ $jumlahPerHalaman[$kunci] ?? 0 }}
                    </span>
                </a>
            @endforeach
        </div>

        <form method="GET" class="mt-3">
            <input type="hidden" name="halaman" value="{{ $halaman }}">
            <label class="relative block">
                <span class="sr-only">Cari blok</span>
                <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="cari" value="{{ $cari }}" data-cari
                       placeholder="Cari label, isi, atau kunci blok…" class="isian pl-10">
            </label>
        </form>
    </div>

    <form id="form-konten" method="POST" action="{{ route('panel.konten.update') }}" class="space-y-3">
        @csrf
        @method('PUT')
        <input type="hidden" name="halaman" value="{{ $halaman }}">

        @forelse ($blok as $b)
            <section class="kartu p-4">
                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                    <label for="blok-{{ $b->id }}" class="text-[0.8125rem] font-semibold text-slate-800">
                        {{ $b->label ?: $b->key }}
                    </label>

                    <span class="flex items-center gap-2">
                        <span @class([
                            'rounded-full px-2 py-0.5 text-[0.7rem] font-medium',
                            'bg-ungu-500/10 text-ungu-700' => $b->type === 'html',
                            'bg-mint-500/15 text-emerald-700' => $b->type === 'image',
                            'bg-slate-100 text-slate-500' => $b->type === 'text',
                        ])>{{ $b->type }}</span>
                        <code class="text-[0.7rem] text-slate-400">{{ $b->key }}</code>
                    </span>
                </div>

                @if ($b->type === 'image')
                    <div class="flex items-start gap-3">
                        @if ($b->value)
                            <img src="{{ asset($b->value) }}" alt="" class="h-16 w-24 shrink-0 rounded-lg object-cover shadow-[var(--shadow-timbul-kecil)]">
                        @endif
                        <input id="blok-{{ $b->id }}" name="nilai[{{ $b->id }}]" class="isian"
                               value="{{ old('nilai.'.$b->id, $b->value) }}"
                               placeholder="assets/images/contoh.png">
                    </div>
                    <p class="mt-1 text-[0.75rem] text-slate-400">Jalur berkas relatif terhadap public/.</p>
                @elseif ($b->type === 'html')
                    <textarea id="blok-{{ $b->id }}" name="nilai[{{ $b->id }}]" rows="3"
                              class="isian font-mono text-[0.8125rem]">{{ old('nilai.'.$b->id, $b->value) }}</textarea>
                    <p class="mt-1 text-[0.75rem] text-slate-400">
                        Dicetak apa adanya tanpa disaring — tag HTML di sini ikut tampil sebagai markup.
                    </p>
                @else
                    <textarea id="blok-{{ $b->id }}" name="nilai[{{ $b->id }}]" rows="2"
                              class="isian">{{ old('nilai.'.$b->id, $b->value) }}</textarea>
                @endif
            </section>
        @empty
            <div class="kartu px-4 py-16 text-center">
                <p class="text-sm font-medium text-slate-600">
                    {{ $cari !== '' ? 'Tidak ada blok yang cocok' : 'Halaman ini belum punya blok' }}
                </p>
            </div>
        @endforelse
    </form>
</x-panel.layout>
