@php
    $tautanUrut = fn (string $kolom) => request()->fullUrlWithQuery([
        'urut' => $kolom,
        'arah' => ($urut === $kolom && $arah === 'desc') ? 'asc' : 'desc',
    ]);
@endphp

<x-panel.layout judul="Pembelian Bahan"
                keterangan="Nota beli bahan. Stok dan kas baru bergerak setelah nota dibukukan.">

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari nota</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nomor nota atau pemasok…" class="isian pl-9">
        </label>

        <select name="status" onchange="this.form.submit()" class="isian w-auto min-w-40">
            <option value="">Semua status</option>
            @foreach (App\Models\ProductionPurchase::STATUSES as $nilai => $label)
                <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
            @endforeach
        </select>

        <input type="hidden" name="urut" value="{{ $urut }}">
        <input type="hidden" name="arah" value="{{ $arah }}">

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($nota->total(), 0, ',', '.') }} nota
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="th">
                            <a href="{{ $tautanUrut('invoice_number') }}" class="hover:text-aksen-600">Nota</a>
                        </th>
                        <th class="th">
                            <a href="{{ $tautanUrut('purchased_at') }}" class="hover:text-aksen-600">Tanggal</a>
                        </th>
                        <th class="th">Gudang</th>
                        <th class="th text-right">
                            <a href="{{ $tautanUrut('total') }}" class="hover:text-aksen-600">Total</a>
                        </th>
                        <th class="th">Status</th>
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($nota as $n)
                        <tr class="transition hover:bg-aksen-50/60">
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $n->invoice_number }}</p>
                                <p class="text-[0.75rem] text-slate-400">
                                    {{ $n->supplier_name ?: 'Tanpa pemasok' }} · {{ $n->items_count }} baris
                                </p>
                            </td>

                            <td class="td text-slate-600">{{ $n->purchased_at?->format('d/m/Y') ?: '—' }}</td>
                            <td class="td text-slate-600">{{ $n->warehouse?->name ?: '—' }}</td>

                            <td class="td text-right font-medium text-slate-800">
                                Rp {{ number_format((float) $n->total, 0, ',', '.') }}
                            </td>

                            <td class="td">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.75rem] font-medium',
                                    'bg-mint-400/15 text-emerald-700' => $n->isPosted(),
                                    'bg-amber-50 text-amber-700' => ! $n->isPosted(),
                                ])>{{ $n->displayStatus() }}</span>
                            </td>

                            <td class="td text-right">
                                <a href="{{ route('panel.pembelian-bahan.show', $n) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-aksen-600 transition hover:bg-aksen-50">
                                    Lihat
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $status ? 'Tidak ada yang cocok' : 'Belum ada nota pembelian' }}
                                </p>
                                <p class="mt-1 text-[0.8125rem] text-slate-400">
                                    Nota masih dibuat di panel lama; layar ini baru menampilkan dan membukukan.
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
