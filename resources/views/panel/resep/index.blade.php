<x-panel.layout judul="Resep Knalpot"
                keterangan="Resep satu knalpot untuk satu type motor. Dari sini modal per knalpot dihitung.">

    <form method="GET" class="kartu mb-4 flex items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1">
            <span class="sr-only">Cari resep</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari placeholder="Cari nama atau kode resep…" class="isian pl-10">
        </label>
        <p class="shrink-0 px-1 text-[0.8125rem] text-slate-500">{{ number_format($resep->total(), 0, ',', '.') }} resep</p>
    </form>

    <div class="kartu overflow-hidden">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="th">Resep</th>
                    <th class="th">Type Motor</th>
                    <th class="th text-right">Bahan</th>
                    <th class="th text-right">Modal / unit</th>
                    <th class="th">Keadaan</th>
                    <th class="th w-px text-right">Aksi</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($resep as $r)
                    <tr class="transition hover:bg-ungu-500/5">
                        <td class="td">
                            <p class="font-medium text-slate-800">{{ $r->name }}</p>
                            <p class="text-[0.75rem] text-slate-400">{{ $r->code ?: 'tanpa kode' }}</p>
                        </td>
                        <td class="td text-slate-600">{{ $r->motorcycleModel?->name ?: '—' }}</td>
                        <td class="td text-right text-slate-600">{{ $r->lines_count }}</td>
                        <td class="td text-right font-medium text-slate-800">
                            Rp {{ number_format($r->totalCostPerUnit(), 0, ',', '.') }}
                        </td>
                        <td class="td">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                'bg-mint-500/15 text-emerald-700' => $r->siap(),
                                'bg-kuning-400/25 text-amber-700' => ! $r->siap(),
                            ])>{{ $r->siap() ? 'Siap' : 'Belum lengkap' }}</span>
                        </td>
                        <td class="td text-right">
                            <a href="{{ route('panel.resep.show', $r) }}"
                               class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">Buka</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-16 text-center">
                            <p class="text-sm font-medium text-slate-600">
                                {{ $cari !== '' ? 'Tidak ada yang cocok' : 'Belum ada resep' }}
                            </p>
                            <p class="mt-1 text-[0.8125rem] text-slate-400">Resep baru masih dibuat di panel lama.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if ($resep->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">{{ $resep->links('panel.partials.paginasi') }}</div>
        @endif
    </div>
</x-panel.layout>
