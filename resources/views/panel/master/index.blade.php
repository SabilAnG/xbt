@php
    /* Nilai isian tambahan ditampilkan sebagai labelnya, bukan angka mentah —
       "Bahan Utama", bukan "utama"; nama brand, bukan id-nya. */
    $labelTambahan = function ($baris, string $medan, array $isian) {
        $nilai = $baris->{$medan};

        if (blank($nilai)) {
            return null;
        }

        if (($isian['jenis'] ?? null) === 'pilihan') {
            if (isset($isian['sumber'])) {
                return $isian['sumber']::find($nilai)?->name;
            }

            return $isian['pilihan'][$nilai] ?? $nilai;
        }

        if (($isian['jenis'] ?? null) === 'angka') {
            $angka = rtrim(rtrim(number_format((float) $nilai, 2, ',', '.'), '0'), ',');

            return trim(($isian['awalan'] ?? '').' '.$angka.($isian['akhiran'] ?? ''));
        }

        return $nilai;
    };
@endphp

<x-panel.layout :judul="$spek['judul']" :keterangan="$spek['petunjuk']">

    <x-slot:aksi>
        <a href="{{ route('panel.master.create', $jenis) }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nama…" class="isian pl-10">
        </label>

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($baris->total(), 0, ',', '.') }} baris
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Nama</th>
                        @foreach ($spek['tambahan'] as $isian)
                            <th class="th">{{ $isian['label'] }}</th>
                        @endforeach
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($baris as $b)
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $b->name }}</p>
                                @if ($spek['slug'])
                                    <p class="text-[0.75rem] text-slate-400">{{ $b->slug }}</p>
                                @endif
                            </td>

                            @foreach ($spek['tambahan'] as $medan => $isian)
                                <td class="td text-slate-600">{{ $labelTambahan($b, $medan, $isian) ?: '—' }}</td>
                            @endforeach

                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-500/15 text-emerald-700' => $b->is_active,
                                    'bg-slate-100 text-slate-500' => ! $b->is_active,
                                ])>{{ $b->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </td>

                            <td class="td text-right">
                                <a href="{{ route('panel.master.edit', [$jenis, $b->id]) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">
                                    Ubah
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + count($spek['tambahan']) }}" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' ? 'Tidak ada yang cocok' : 'Belum ada data' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($baris->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">
                {{ $baris->links('panel.partials.paginasi') }}
            </div>
        @endif
    </div>
</x-panel.layout>
