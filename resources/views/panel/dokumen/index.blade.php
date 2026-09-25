<x-panel.layout :judul="$spek['judul']" :keterangan="$spek['petunjuk']">

    <x-slot:aksi>
        <a href="{{ route('panel.dokumen.create', $jenis) }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Nota Baru
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nomor atau {{ strtolower($spek['pihak']['label']) }}…" class="isian pl-10">
        </label>

        <select name="status" onchange="this.form.submit()" class="isian w-auto min-w-40">
            <option value="">Semua status</option>
            <option value="draft" @selected($status === 'draft')>Draft</option>
            <option value="posted" @selected($status === 'posted')>Dibukukan</option>
        </select>

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($nota->total(), 0, ',', '.') }} nota
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Nota</th>
                        <th class="th">Tanggal</th>
                        @if ($spek['total'])
                            <th class="th text-right">Nilai</th>
                        @endif
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($nota as $n)
                        @php $dibukukan = $n->status === 'posted'; @endphp
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $n->{$spek['nomor']} ?: '—' }}</p>
                                <p class="text-[0.75rem] text-slate-400">
                                    {{ $n->{$spek['pihak']['kolom']} ?: 'Tanpa '.strtolower($spek['pihak']['label']) }}
                                    @if ($spek['baris']) · {{ $n->items_count }} baris @endif
                                </p>
                            </td>

                            <td class="td text-slate-600">{{ $n->{$spek['tanggal']}?->format('d/m/Y') ?: '—' }}</td>

                            @if ($spek['total'])
                                <td class="td text-right font-medium text-slate-800">
                                    Rp {{ number_format((float) $n->{$spek['total']}, 0, ',', '.') }}
                                </td>
                            @endif

                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-500/15 text-emerald-700' => $dibukukan,
                                    'bg-kuning-400/25 text-amber-700' => ! $dibukukan,
                                ])>{{ $dibukukan ? 'Dibukukan' : 'Draft' }}</span>
                            </td>

                            <td class="td text-right">
                                <a href="{{ route('panel.dokumen.show', [$jenis, $n->id]) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $status ? 'Tidak ada yang cocok' : 'Belum ada nota' }}
                                </p>
                                <p class="mt-1 text-[0.8125rem] text-slate-400">
                                    Tekan tombol Nota Baru untuk membuat draft pertama.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($nota->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">
                {{ $nota->links('panel.partials.paginasi') }}
            </div>
        @endif
    </div>
</x-panel.layout>
