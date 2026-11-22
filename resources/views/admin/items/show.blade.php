@extends('layouts.admin', ['title' => $item->name, 'subtitle' => $item->sku.' · kartu stok'])

@section('actions')
    <a href="{{ route('admin.items.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.items.edit', $item) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-admin.stat label="Sisa stok" :value="qty($item->stock).' '.$item->unit" :tone="match ($item->stockStatus()) { 'HABIS' => 'danger', 'MENIPIS' => 'warning', default => 'success' }" :hint="'Status '.$item->stockStatus().' · minimum '.qty($item->min_stock)" />
        <x-admin.stat label="HPP / unit" :value="rupiah($item->cost_price)" />
        <x-admin.stat label="Harga offline" :value="rupiah($item->sell_price)" />
        <x-admin.stat label="Harga online" :value="rupiah($item->sell_price_online)" />
        <x-admin.stat label="Nilai stok (HPP)" :value="rupiah($item->stockValue())" />
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Detail</h2></div>
            <dl class="divide-y divide-zinc-100 text-sm">
                @foreach (['Stok awal' => qty($item->opening_stock), '+ Produksi' => qty($item->producedQty()), '− Terjual' => qty($item->soldQty()), 'Model motor' => $item->model_motor, 'Finishing' => $item->finishing, 'Tipe' => $item->variant, 'Ukuran' => $item->size, 'Merek' => $item->brand, 'Catatan' => $item->notes] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-zinc-500">{{ $label }}</dt>
                        <dd class="text-right font-medium text-zinc-800">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Bahan per unit — {{ $item->formula?->name ?? 'belum ada resep' }}</h2>
                @if ($item->formula)
                    <a href="{{ route('admin.formulas.edit', $item->formula) }}" class="link text-xs">Ubah resep</a>
                @endif
            </div>
            <div class="max-h-80 overflow-y-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="num">Qty / knalpot</th><th>Berlaku</th></tr></thead>
                    <tbody>
                    @forelse ($item->formula?->materials?->filter(fn ($line) => $line->appliesToBrand($item->brand)) ?? [] as $line)
                        <tr>
                            <td>{{ $line->material?->name }}</td>
                            <td class="num">{{ qty($line->qty) }} {{ $line->material?->unit }}</td>
                            <td class="text-xs text-zinc-500">{{ $line->brand ?? 'Semua merek' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-zinc-500">Produk ini belum punya resep, jadi belum bisa diproduksi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Kartu stok</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Jenis</th><th>Dokumen</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td>{{ \App\Models\StockMovement::TYPES[$movement->type] ?? $movement->type }}</td>
                        <td class="font-mono text-xs">{{ $movement->notes }}</td>
                        <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in) : '' }}</td>
                        <td class="num text-red-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out) : '' }}</td>
                        <td class="num font-semibold">{{ qty($movement->balance_after) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-zinc-500">Belum ada pergerakan stok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    {{ $movements->links() }}

    @if ($movements->isEmpty())
        <div class="flex justify-end">
            <x-admin.delete-button :action="route('admin.items.destroy', $item)" label="Hapus produk" />
        </div>
    @endif
@endsection
