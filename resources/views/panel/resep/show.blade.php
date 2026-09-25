<x-panel.layout :judul="$resep->name" :keterangan="$resep->motorcycleModel?->name">

    <x-slot:aksi>
        <a href="{{ route('panel.resep.index') }}" class="tombol tombol-hening">Kembali</a>
        <button type="submit" form="form-resep" class="tombol tombol-utama">Simpan</button>
    </x-slot:aksi>

    {{-- Ringkasan modal --}}
    <section class="kartu mb-4 grid gap-4 p-5 sm:grid-cols-4">
        @foreach ([
            'Modal bahan' => $resep->materialCostPerUnit(),
            'Modal jasa' => $resep->serviceCostPerUnit(),
            'Total per unit' => $resep->totalCostPerUnit(),
        ] as $label => $nilai)
            <div>
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">{{ $label }}</p>
                <p @class(['mt-1 font-bold text-slate-900', 'text-lg' => $label === 'Total per unit'])>
                    Rp {{ number_format($nilai, 0, ',', '.') }}
                </p>
            </div>
        @endforeach

        <div>
            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.07em] text-slate-400">Keadaan</p>
            <p class="mt-1">
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-[0.8125rem] font-medium',
                    'bg-mint-500/15 text-emerald-700' => $resep->siap(),
                    'bg-kuning-400/25 text-amber-700' => ! $resep->siap(),
                ])>{{ $resep->siap() ? 'Siap dipakai' : 'Belum lengkap' }}</span>
            </p>
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Bahan --}}
            <section class="kartu overflow-hidden">
                <h2 class="px-5 pb-2 pt-5 text-sm font-semibold text-slate-800">Bahan</h2>

                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="th">Bahan</th>
                            <th class="th">Bagian</th>
                            <th class="th text-right">Ukuran</th>
                            <th class="th text-right">Modal</th>
                            <th class="th w-px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($resep->lines as $baris)
                            <tr>
                                <td class="td">
                                    <p class="font-medium text-slate-800">{{ $baris->item?->name ?: 'Bahan belum dipilih' }}</p>
                                    <p class="text-[0.75rem] text-slate-400">{{ $baris->item?->sku }}</p>
                                </td>
                                <td class="td text-slate-600">{{ $baris->component?->name ?: '—' }}</td>
                                <td class="td text-right text-slate-600">
                                    @if ($baris->input_mode === 'length')
                                        {{ rtrim(rtrim(number_format((float) $baris->piece_length_mm, 1, ',', '.'), '0'), ',') }} mm
                                        &times; {{ rtrim(rtrim(number_format((float) $baris->piece_count, 2, ',', '.'), '0'), ',') }}
                                    @elseif ($baris->input_mode === 'rect')
                                        {{ rtrim(rtrim(number_format((float) $baris->piece_length_mm, 0, ',', '.'), '0'), ',') }}
                                        &times; {{ rtrim(rtrim(number_format((float) $baris->piece_width_mm, 0, ',', '.'), '0'), ',') }} mm
                                        &times; {{ rtrim(rtrim(number_format((float) $baris->piece_count, 2, ',', '.'), '0'), ',') }}
                                    @else
                                        {{ rtrim(rtrim(number_format((float) $baris->piece_count, 2, ',', '.'), '0'), ',') }} buah
                                    @endif
                                </td>
                                <td class="td text-right font-medium text-slate-800">
                                    Rp {{ number_format($baris->cost(), 0, ',', '.') }}
                                </td>
                                <td class="td text-right">
                                    <form method="POST" action="{{ route('panel.resep.hapus-baris', [$resep, $baris]) }}"
                                          onsubmit="return confirm('Hapus baris ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-[0.75rem] text-merah-500">hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">Resep ini belum punya bahan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            {{-- Jasa --}}
            <section class="kartu overflow-hidden">
                <h2 class="px-5 pb-2 pt-5 text-sm font-semibold text-slate-800">Jasa produksi</h2>

                <table class="w-full border-collapse">
                    <tbody>
                        @forelse ($resep->services as $jasa)
                            <tr>
                                <td class="td">{{ $jasa->service?->name ?: '—' }}</td>
                                <td class="td text-right text-slate-600">
                                    {{ rtrim(rtrim(number_format((float) $jasa->qty, 2, ',', '.'), '0'), ',') }}
                                    {{ $jasa->service?->unit }}
                                </td>
                                <td class="td w-px text-right">
                                    <form method="POST" action="{{ route('panel.resep.hapus-jasa', [$resep, $jasa]) }}"
                                          onsubmit="return confirm('Hapus jasa ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-[0.75rem] text-merah-500">hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-8 text-center text-sm text-slate-500">Belum ada jasa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <div class="space-y-4">
            @include('panel.resep.sunting')
        </div>
    </div>
</x-panel.layout>
