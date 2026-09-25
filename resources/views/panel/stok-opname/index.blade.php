<x-panel.layout judul="Hitung Ulang Stok"
                keterangan="Menghitung fisik bahan di satu gudang, lalu mengoreksi selisihnya.">

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari opname</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nomor opname atau penghitung…" class="isian pl-10">
        </label>

        <select name="status" onchange="this.form.submit()" class="isian w-auto min-w-40">
            <option value="">Semua status</option>
            @foreach (App\Models\ProductionItemOpname::STATUSES as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($opname->total(), 0, ',', '.') }} opname
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">Opname</th>
                        <th class="th">Tanggal</th>
                        <th class="th">Gudang</th>
                        <th class="th text-right">Baris</th>
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($opname as $o)
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $o->opname_number }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $o->counted_by ?: 'Tanpa penghitung' }}</p>
                            </td>
                            <td class="td text-slate-600">{{ $o->opname_date?->format('d/m/Y') ?: '—' }}</td>
                            <td class="td text-slate-600">{{ $o->warehouse?->name ?: '—' }}</td>
                            <td class="td text-right text-slate-700">{{ $o->items_count }}</td>
                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-500/15 text-emerald-700' => $o->isPosted(),
                                    'bg-kuning-400/25 text-amber-700' => ! $o->isPosted(),
                                ])>{{ $o->displayStatus() }}</span>
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('panel.stok-opname.show', $o) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $status ? 'Tidak ada yang cocok' : 'Belum ada opname' }}
                                </p>
                                <p class="mt-1 text-[0.8125rem] text-slate-400">
                                    Opname masih dibuat di panel lama; layar ini menampilkan dan membukukan.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($opname->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">
                {{ $opname->links('panel.partials.paginasi') }}
            </div>
        @endif
    </div>
</x-panel.layout>
