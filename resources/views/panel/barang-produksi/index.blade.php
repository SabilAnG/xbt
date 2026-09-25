@php
    /** Tautan pengurutan: klik kolom yang sama membalik arahnya. */
    $tautanUrut = fn (string $kolom) => request()->fullUrlWithQuery([
        'urut' => $kolom,
        'arah' => ($urut === $kolom && $arah === 'asc') ? 'desc' : 'asc',
    ]);
@endphp

<x-panel.layout judul="Barang Produksi"
                keterangan="Bahan untuk membuat knalpot: pipa, plat, baut, pegas.">

    <x-slot:aksi>
        <a href="{{ route('panel.barang-produksi.create') }}" class="tombol tombol-utama">
            <x-panel.ikon nama="tambah" kelas="h-4 w-4" />
            Tambah Barang
        </a>
    </x-slot:aksi>

    {{-- Saringan --}}
    <form method="GET" class="kartu mb-4 flex flex-wrap items-center gap-3 p-3">
        <label class="relative min-w-0 flex-1 basis-56">
            <span class="sr-only">Cari barang</span>
            <x-panel.ikon nama="cari" kelas="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" name="cari" value="{{ $cari }}" data-cari
                   placeholder="Cari nama atau SKU…" class="isian pl-9">
        </label>

        <select name="jenis" onchange="this.form.submit()" class="isian w-auto min-w-44">
            <option value="">Semua jenis</option>
            @foreach ($daftarJenis as $id => $nama)
                <option value="{{ $id }}" @selected((string) $jenis === (string) $id)>{{ $nama }}</option>
            @endforeach
        </select>

        {{-- Pengurutan ikut terbawa saat menyaring, jadi tidak diam-diam kembali
             ke urutan bawaan. --}}
        <input type="hidden" name="urut" value="{{ $urut }}">
        <input type="hidden" name="arah" value="{{ $arah }}">

        <p class="ml-auto shrink-0 px-1 text-[0.8125rem] text-slate-500">
            {{ number_format($barang->total(), 0, ',', '.') }} barang
        </p>
    </form>

    {{-- Tabel --}}
    <div class="kartu overflow-hidden">
        <div class="max-h-[calc(100dvh-16rem)] overflow-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach (['sku' => 'SKU', 'name' => 'Barang'] as $kolom => $label)
                            <th class="th">
                                <a href="{{ $tautanUrut($kolom) }}" class="inline-flex items-center gap-1 hover:text-aksen-600">
                                    {{ $label }}
                                    @if ($urut === $kolom)
                                        <span class="text-aksen-500">{{ $arah === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                        @endforeach

                        <th class="th">Konversi</th>

                        @foreach (['cost_price' => 'Harga Beli', 'stock' => 'Stok'] as $kolom => $label)
                            <th class="th text-right">
                                <a href="{{ $tautanUrut($kolom) }}" class="inline-flex items-center gap-1 hover:text-aksen-600">
                                    {{ $label }}
                                    @if ($urut === $kolom)
                                        <span class="text-aksen-500">{{ $arah === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                        <th class="th w-px text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($barang as $b)
                        <tr class="transition hover:bg-aksen-50/60">
                            <td class="td font-mono text-[0.8125rem] text-slate-500">{{ $b->sku }}</td>

                            <td class="td">
                                <p class="font-medium text-slate-800">{{ $b->name }}</p>
                                <p class="text-[0.75rem] text-slate-400">
                                    {{ collect([
                                        $b->category?->name,
                                        $b->material ? $b->displayMaterial() : null,
                                        $b->displayDimensions() !== '—' ? $b->displayDimensions() : null,
                                    ])->filter()->implode(' · ') ?: '—' }}
                                </p>
                            </td>

                            <td class="td text-slate-500">{{ $b->conversionLabel() }}</td>

                            <td class="td text-right">
                                <p class="font-medium text-slate-800">Rp {{ number_format((float) $b->cost_price, 0, ',', '.') }}</p>
                                <p class="text-[0.75rem] text-slate-400">{{ $b->displayBasePrice() }}</p>
                            </td>

                            <td class="td text-right">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-[0.8125rem] font-medium',
                                    'bg-rose-50 text-rose-600' => (float) $b->stock <= (float) $b->min_stock,
                                    'bg-mint-400/10 text-emerald-700' => (float) $b->stock > (float) $b->min_stock,
                                ])>
                                    {{ $b->displayStock() }}
                                </span>
                            </td>
                            <td class="td text-right">
                                <a href="{{ route('panel.barang-produksi.edit', $b) }}"
                                   class="rounded-lg px-2.5 py-1 text-[0.8125rem] font-medium text-aksen-600 transition hover:bg-aksen-50">
                                    Ubah
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <p class="text-sm font-medium text-slate-600">
                                    {{ $cari !== '' || $jenis ? 'Tidak ada yang cocok' : 'Belum ada barang produksi' }}
                                </p>
                                <p class="mt-1 text-[0.8125rem] text-slate-400">
                                    {{ $cari !== '' || $jenis
                                        ? 'Coba kata lain, atau kosongkan saringannya.'
                                        : 'Mulai dari yang paling sering dipakai: pipa, plat, lalu aksesoris.' }}
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
