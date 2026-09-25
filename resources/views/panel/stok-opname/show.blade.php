<x-panel.layout :judul="$opname->opname_number"
                :keterangan="$opname->warehouse?->name ? 'Gudang '.$opname->warehouse->name : null">

    <x-slot:aksi>
        <a href="{{ route('panel.stok-opname.index') }}" class="tombol tombol-hening">Kembali</a>

        @if ($opname->isPosted())
            <form method="POST" action="{{ route('panel.stok-opname.batalkan', $opname) }}"
                  onsubmit="return confirm('Batalkan pembukuan? Stok akan dihitung ulang.')">
                @csrf
                <button class="tombol bg-white text-amber-700 shadow-[var(--shadow-timbul-kecil)]">Batalkan pembukuan</button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.stok-opname.bukukan', $opname) }}"
                  onsubmit="return confirm('Bukukan opname ini? Hanya baris yang selisih yang mengoreksi stok.')">
                @csrf
                <button class="tombol tombol-utama">Bukukan</button>
            </form>
        @endif
    </x-slot:aksi>

    <section class="kartu mb-4 grid gap-4 p-5 sm:grid-cols-4">
        @foreach ([
            'Status' => null,
            'Tanggal' => $opname->opname_date?->format('d F Y') ?: '—',
            'Dihitung oleh' => $opname->counted_by ?: '—',
            'Baris selisih' => $opname->discrepancyCount().' dari '.$opname->items->count(),
        ] as $label => $isi)
            <div>
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>

                @if ($label === 'Status')
                    <p class="mt-1">
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[0.8125rem] font-medium',
                            'bg-mint-500/15 text-emerald-700' => $opname->isPosted(),
                            'bg-kuning-400/25 text-amber-700' => ! $opname->isPosted(),
                        ])>{{ $opname->displayStatus() }}</span>
                    </p>
                @else
                    <p class="mt-1 text-sm font-medium text-slate-800">{{ $isi }}</p>
                @endif
            </div>
        @endforeach
    </section>

    <div class="kartu overflow-hidden">
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th class="th">Bahan</th>
                    <th class="th text-right">Menurut sistem</th>
                    <th class="th text-right">Hasil hitung</th>
                    <th class="th text-right">Selisih</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($opname->items as $baris)
                    @php $selisih = (float) $baris->difference; @endphp
                    <tr @class(['bg-kuning-400/10' => abs($selisih) > 0.0001])>
                        <td class="td">
                            <p class="font-medium text-slate-800">{{ $baris->item?->name ?: 'Bahan terhapus' }}</p>
                            <p class="text-[0.75rem] text-slate-400">{{ $baris->item?->sku }}</p>
                        </td>
                        <td class="td text-right text-slate-600">{{ $baris->item?->formatBase((float) $baris->system_qty) }}</td>
                        <td class="td text-right text-slate-800">{{ $baris->item?->formatBase((float) $baris->physical_qty) }}</td>
                        <td class="td text-right">
                            @if (abs($selisih) > 0.0001)
                                <span @class([
                                    'font-semibold',
                                    'text-emerald-600' => $selisih > 0,
                                    'text-merah-500' => $selisih < 0,
                                ])>
                                    {{ $selisih > 0 ? '+' : '' }}{{ $baris->item?->formatBase($selisih) }}
                                </span>
                            @else
                                <span class="text-slate-300">cocok</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-sm text-slate-500">
                            Opname ini belum punya baris hitungan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($opname->notes)
        <section class="kartu mt-4 p-5">
            <h2 class="mb-1 text-sm font-semibold text-slate-800">Catatan</h2>
            <p class="whitespace-pre-line text-sm text-slate-600">{{ $opname->notes }}</p>
        </section>
    @endif
</x-panel.layout>
