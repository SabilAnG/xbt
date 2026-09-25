@php
    $tautanUrut = fn (string $kolom) => request()->fullUrlWithQuery([
        'urut' => $kolom,
        'arah' => ($urut === $kolom && $arah === 'asc') ? 'desc' : 'asc',
    ]);
@endphp

<x-panel.layout judul="Stok Barang" keterangan="Barang jadi yang dijual. Stok hanya bergerak lewat nota, bukan diketik.">

    <x-slot:aksi>
        <a href="{{ route('panel.stok-barang.create') }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah Barang
        </a>
    </x-slot:aksi>

    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari barang</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nama atau SKU…" class="isian pl-10">
        </label>

        <select name="kategori" onchange="this.form.submit()" class="isian w-auto min-w-44">
            <option value="">Semua kategori</option>
            @foreach ($daftarKategori as $id => $nama)
                <option value="{{ $id }}" @selected((string) $kategori === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>

        <label class="flex shrink-0 items-center gap-2 text-[0.8125rem] text-slate-600">
            <input type="checkbox" name="menipis" value="1" onchange="this.form.submit()" @checked($menipis)
                   class="rounded border-slate-300 text-ungu-500 focus:ring-ungu-400">
            Stok menipis
        </label>

        <input type="hidden" name="urut" value="{{ $urut }}">
        <input type="hidden" name="arah" value="{{ $arah }}">

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($barang->total(), 0, ',', '.') }} barang
        </p>
    </form>

    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach (['sku' => 'SKU', 'name' => 'Barang'] as $k => $l)
                            <th class="th"><a href="{{ $tautanUrut($k) }}" class="hover:text-ungu-600">{{ $l }}</a></th>
                        @endforeach
                        @foreach (['cost_price' => 'Modal', 'sell_price' => 'Jual', 'stock' => 'Stok'] as $k => $l)
                            <th class="th text-right"><a href="{{ $tautanUrut($k) }}" class="hover:text-ungu-600">{{ $l }}</a></th>
                        @endforeach
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($barang as $b)
                        <tr class="transition hover:bg-ungu-500/5">
                            <td class="td font-mono text-[0.8125rem] text-slate-500">{{ $b->sku }}</td>
                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $b->name }}</p>
                                <p class="text-[0.75rem] text-slate-400">
                                    {{ collect([$b->category?->name, $b->type?->name])->filter()->implode(' · ') ?: '—' }}
                                </p>
                            </td>
                            <td class="td text-right text-slate-600">Rp {{ number_format((float) $b->cost_price, 0, ',', '.') }}</td>
                            <td class="td text-right font-medium text-slate-800">Rp {{ number_format((float) $b->sell_price, 0, ',', '.') }}</td>
                            <td class="td text-right">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.8125rem] font-medium',
                                    'bg-merah-500/10 text-merah-500' => (float) $b->stock <= (float) $b->min_stock,
                                    'bg-mint-500/15 text-emerald-700' => (float) $b->stock > (float) $b->min_stock,
                                ])>
                                    {{ rtrim(rtrim(number_format((float) $b->stock, 2, ',', '.'), '0'), ',') }}
                                    {{ $b->unit }}
                                </span>
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('panel.stok-barang.edit', $b) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-ungu-600 transition hover:bg-ungu-500/10">Ubah</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $kategori || $menipis ? 'Tidak ada yang cocok' : 'Belum ada barang' }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($barang->hasPages())
            <div class="border-t border-slate-100 px-4 py-2.5 text-[0.8125rem]">
                {{ $barang->links('panel.partials.paginasi') }}
            </div>
        @endif
    </div>
</x-panel.layout>
