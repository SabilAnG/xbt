@extends('layouts.admin', ['title' => $part->name, 'subtitle' => $part->code.' · '.$part->groupLabel()])

@section('actions')
    <a href="{{ route('admin.components.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.components.edit', $part) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-admin.stat label="Stok di gudang setengah jadi" :value="qty((float) $part->stock).' '.$part->unit" :tone="match ($part->stockStatus()) { 'HABIS' => 'danger', 'MENIPIS' => 'warning', default => 'success' }" :hint="'Status '.$part->stockStatus()" />
        <x-admin.stat label="Stok minimum" :value="qty((float) $part->min_stock).' '.$part->unit" />
        <x-admin.stat label="Biaya bahan / unit" :value="rupiah($part->materialCost())" hint="Dari harga rata-rata bahan baku" />
        <x-admin.stat label="Nilai stok" :value="rupiah($part->stockValue())" />
    </section>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.warehouses.movements.create', ['component' => $part->id, 'type' => 'masuk']) }}" class="btn">+ Catat hasil dibuat</a>
        <a href="{{ route('admin.warehouses.movements.create', ['component' => $part->id, 'type' => 'keluar']) }}" class="btn">− Dipakai rakit</a>
        <a href="{{ route('admin.warehouses.movements.create', ['component' => $part->id, 'type' => 'penyesuaian']) }}" class="btn">± Penyesuaian</a>
    </div>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Bahan baku penyusun (per 1 {{ $part->unit }})</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th class="num">Jumlah</th><th class="num">Harga rata-rata</th><th class="num">Subtotal</th><th class="num">Stok bahan</th></tr></thead>
                <tbody>
                @forelse ($part->materials as $line)
                    <tr>
                        <td><a href="{{ route('admin.materials.show', $line->material_id) }}" class="link">{{ $line->material?->name }}</a></td>
                        <td class="num">{{ qty((float) $line->qty, 4) }} {{ $line->material?->unit }}</td>
                        <td class="num">{{ rupiah($line->material?->cost_price) }}</td>
                        <td class="num">{{ rupiah((float) $line->qty * (float) $line->material?->cost_price) }}</td>
                        <td class="num">{{ qty(round((float) $line->material?->stock, 2)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-500">Belum ada bahan penyusun. <a href="{{ route('admin.components.edit', $part) }}" class="link">Isi sekarang</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('admin.warehouses.partials.movements', ['movements' => $movements, 'title' => 'Kartu stok komponen', 'showComponent' => false])
    {{ $movements->links() }}
@endsection
