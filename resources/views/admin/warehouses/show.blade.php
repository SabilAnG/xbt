@extends('layouts.admin', ['title' => $warehouse->name, 'subtitle' => $warehouse->typeDescription()])

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn">Semua gudang</a>
    <a href="{{ route('admin.materials.create', ['gudang' => $warehouse->slug()]) }}" class="btn btn-primary">+ Bahan</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Nilai stok" :value="rupiah($stocks->sum('value'))" tone="brand" />
        <x-admin.stat label="Jenis bahan berstok" :value="$stocks->where('qty', '>', 0)->count()" />
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Isi gudang</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Kode</th><th>Bahan</th><th>Kategori</th><th class="num">Stok</th><th class="num">Harga rata-rata</th><th class="num">Nilai</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($stocks as $stock)
                    @php $material = $stock->material; @endphp
                    <tr>
                        <td class="font-mono text-xs text-slate-500">{{ $material->code }}</td>
                        <td><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->label() }}</a></td>
                        <td class="text-xs text-slate-500">{{ $material->category?->name ?? '—' }}</td>
                        <td class="num font-semibold whitespace-nowrap">
                            {{ qty($stock->qty, 2) }} {{ $material->stockUnit() }}
                            @if ($material->isPipe())<p class="text-xs font-normal text-slate-500">{{ $pieceCounts[$material->id] ?? 0 }} batang</p>@endif
                        </td>
                        <td class="num">{{ rupiah($material->unitCost()) }}/{{ $material->stockUnit() }}</td>
                        <td class="num">{{ rupiah($stock->value) }}</td>
                        <td><x-admin.status :value="$material->stockStatus()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Gudang ini masih kosong.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Kartu stok gudang</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Bahan</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td>{{ $movement->material->label() }}</td>
                        <td class="text-xs">{{ $movement->typeLabel() }}</td>
                        <td class="text-xs text-slate-500">{{ $movement->notes }}</td>
                        <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in, 2) : '' }}</td>
                        <td class="num text-rose-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out, 2) : '' }}</td>
                        <td class="num font-semibold">{{ qty($movement->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada pergerakan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $movements->links() }}</div>@endif
    </section>
@endsection
