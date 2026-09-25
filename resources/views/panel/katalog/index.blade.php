<x-panel.layout judul="Katalog Website" keterangan="Produk yang tampil di halaman produk situs publik.">

    <x-slot:aksi>
        <a href="{{ route('panel.katalog.create') }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah Produk
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari produk</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari placeholder="Cari nama atau slug…" class="isian pl-10">
        </label>
        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($produk->total(), 0, ',', '.') }} produk
        </p>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($produk as $p)
            @php $sampul = $p->images()->orderBy('sort_order')->value('path'); @endphp

            <a href="{{ route('panel.katalog.edit', $p) }}" class="kartu group overflow-hidden transition hover:shadow-[var(--shadow-angkat)]">
                <div class="aspect-[4/3] bg-lantai">
                    @if ($sampul)
                        <img src="{{ asset($sampul) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                    @else
                        <div class="grid h-full place-items-center text-[0.8125rem] text-slate-400">Belum ada gambar</div>
                    @endif
                </div>

                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 truncate font-semibold text-slate-800 group-hover:text-ungu-600">{{ $p->name }}</p>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium',
                            'bg-mint-500/15 text-emerald-700' => $p->is_active,
                            'bg-slate-100 text-slate-500' => ! $p->is_active,
                        ])>{{ $p->is_active ? 'Tampil' : 'Disembunyikan' }}</span>
                    </div>

                    <p class="mt-0.5 truncate text-[0.75rem] text-slate-400">{{ $p->fitment ?: $p->slug }}</p>

                    <div class="mt-2 flex items-baseline justify-between">
                        <p class="font-bold text-slate-900">
                            {{ $p->price ? 'Rp '.number_format((float) $p->price, 0, ',', '.') : '—' }}
                        </p>
                        <p class="text-[0.75rem] text-slate-400">{{ $p->images_count }} gambar</p>
                    </div>
                </div>
            </a>
        @empty
            <div class="kartu col-span-full px-4 py-16 text-center">
                <p class="text-sm font-medium text-slate-600">{{ $cari !== '' ? 'Tidak ada yang cocok' : 'Belum ada produk' }}</p>
            </div>
        @endforelse
    </div>

    @if ($produk->hasPages())
        <div class="kartu mt-4 px-4 py-2.5 text-[0.8125rem]">
            {{ $produk->links('panel.partials.paginasi') }}
        </div>
    @endif
</x-panel.layout>
