@extends('layouts.admin', ['title' => $material->name, 'subtitle' => $material->sku.' · '.($material->category?->name ?? 'tanpa kategori')])

@section('actions')
    <a href="{{ route('admin.materials.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.materials.edit', $material) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-admin.stat label="Sisa stok" :value="qty(round((float) $material->stock, 2)).' '.$material->unit" :tone="match ($material->stockStatus()) { 'HABIS' => 'danger', 'MENIPIS' => 'warning', default => 'success' }" :hint="'Status '.$material->stockStatus()" />
        <x-admin.stat label="Stok minimum" :value="qty($material->min_stock).' '.$material->unit" />
        <x-admin.stat label="Harga rata-rata" :value="rupiah($material->cost_price)" hint="Rata-rata tertimbang pembelian" />
        <x-admin.stat label="Dipakai di resep" :value="$material->formulaLines->pluck('formula.name')->unique()->count()" :hint="$material->formulaLines->pluck('formula.name')->unique()->implode(', ')" />
    </section>

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.purchases.create', ['material' => $material->id]) }}" class="btn">+ Catat pembelian</a>
        <a href="{{ route('admin.adjustments.create', ['material' => $material->id]) }}" class="btn">± Penyesuaian stok</a>
    </div>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Kartu stok bahan</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td class="whitespace-nowrap">{{ \App\Models\MaterialMovement::TYPES[$movement->type] ?? $movement->type }}</td>
                        <td class="text-xs">{{ $movement->notes }}</td>
                        <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in) : '' }}</td>
                        <td class="num text-rose-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out) : '' }}</td>
                        <td class="num font-semibold">{{ qty($movement->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500">Belum ada pergerakan stok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $movements->links() }}

    @if ($movements->isEmpty() && $material->formulaLines->isEmpty())
        <div class="flex justify-end">
            <x-admin.delete-button :action="route('admin.materials.destroy', $material)" label="Hapus bahan" />
        </div>
    @endif
@endsection
