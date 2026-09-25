{{--
    Paginasi.

    Sengaja sederhana: dua tombol dan satu keterangan. Deretan nomor halaman
    berguna kalau orang tahu isi halaman 7 — di daftar barang, tidak ada yang
    tahu. Yang dicari orang justru pencarian, bukan halaman ke sekian.
--}}
@if ($paginator->hasPages())
    <div class="flex items-center justify-between gap-3">
        <p class="text-slate-500">
            {{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}
            dari {{ number_format($paginator->total(), 0, ',', '.') }}
        </p>

        <div class="flex items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="tombol cursor-default border border-slate-100 text-slate-300">Sebelumnya</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="tombol tombol-hening">Sebelumnya</a>
            @endif

            <span class="px-1 text-slate-400">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="tombol tombol-hening">Berikutnya</a>
            @else
                <span class="tombol cursor-default border border-slate-100 text-slate-300">Berikutnya</span>
            @endif
        </div>
    </div>
@endif
