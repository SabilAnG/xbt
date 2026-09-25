@php
    /* Masa tayang lebih menentukan daripada tombol aktif: iklan yang aktif tapi
       sudah lewat tanggalnya tetap tidak tampil di situs. */
    $keadaan = function ($i) {
        if (! $i->is_active) {
            return ['Nonaktif', 'bg-slate-100 text-slate-500'];
        }
        if ($i->starts_at && $i->starts_at->isFuture()) {
            return ['Menunggu', 'bg-kuning-400/25 text-amber-700'];
        }
        if ($i->ends_at && $i->ends_at->isPast()) {
            return ['Habis', 'bg-merah-500/10 text-merah-500'];
        }

        return ['Tayang', 'bg-mint-500/15 text-emerald-700'];
    };
@endphp

<x-panel.layout judul="Iklan" keterangan="Banner yang tampil di situs publik, dengan masa tayangnya sendiri.">

    <x-slot:aksi>
        <a href="{{ route('panel.iklan.create') }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah Iklan
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1">
            <span class="sr-only">Cari iklan</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari placeholder="Cari judul atau pemasang…" class="isian pl-10">
        </label>
        <p class="shrink-0 px-1 text-[0.8125rem] text-slate-500">{{ number_format($iklan->total(), 0, ',', '.') }} iklan</p>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($iklan as $i)
            @php [$labelKeadaan, $warnaKeadaan] = $keadaan($i); @endphp

            <a href="{{ route('panel.iklan.edit', $i) }}" class="kartu group overflow-hidden transition hover:shadow-[var(--shadow-angkat)]">
                <div class="aspect-[3/1] bg-lantai">
                    @if ($i->image_path)
                        <img src="{{ asset($i->image_path) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                    @else
                        <div class="grid h-full place-items-center text-[0.8125rem] text-slate-400">Tanpa gambar</div>
                    @endif
                </div>

                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 flex-1 truncate font-semibold text-slate-800 group-hover:text-ungu-600">{{ $i->title }}</p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-medium {{ $warnaKeadaan }}">{{ $labelKeadaan }}</span>
                    </div>

                    <p class="mt-0.5 truncate text-[0.75rem] text-slate-400">
                        {{ App\Models\Advertisement::POSITIONS[$i->position] ?? $i->position }}
                    </p>

                    <p class="mt-2 text-[0.75rem] text-slate-500">
                        {{ $i->starts_at?->format('d/m/Y') ?: '—' }} → {{ $i->ends_at?->format('d/m/Y') ?: 'tanpa batas' }}
                        @if ($i->advertiser_name) · {{ $i->advertiser_name }} @endif
                    </p>
                </div>
            </a>
        @empty
            <div class="kartu col-span-full px-4 py-16 text-center">
                <p class="text-sm font-medium text-slate-600">{{ $cari !== '' ? 'Tidak ada yang cocok' : 'Belum ada iklan' }}</p>
            </div>
        @endforelse
    </div>

    @if ($iklan->hasPages())
        <div class="kartu mt-4 px-4 py-2.5 text-[0.8125rem]">{{ $iklan->links('panel.partials.paginasi') }}</div>
    @endif
</x-panel.layout>
