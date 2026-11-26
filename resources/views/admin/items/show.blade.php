@extends('layouts.admin', ['title' => $item->name, 'subtitle' => $item->sku.' · kartu stok'])

@section('actions')
    <a href="{{ route('admin.items.index') }}" class="btn">Kembali</a>
    <a href="{{ route('admin.items.edit', $item) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5">
        <x-admin.stat label="Sisa stok" :value="qty($item->stock).' '.$item->unit" :tone="match ($item->stockStatus()) { 'HABIS' => 'danger', 'MENIPIS' => 'warning', default => 'success' }" :hint="'Status '.$item->stockStatus().' · minimum '.qty($item->min_stock)" />
        <x-admin.stat label="HPP / unit" :value="rupiah($item->cost_price)" />
        <x-admin.stat label="Harga offline" :value="rupiah($item->sell_price)" />
        <x-admin.stat label="Harga online" :value="rupiah($item->sell_price_online)" />
        <x-admin.stat label="Nilai stok (HPP)" :value="rupiah($item->stockValue())" />
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Detail</h2></div>
            <dl class="divide-y divide-slate-100 text-sm">
                @foreach (['Stok awal' => qty($item->opening_stock), '+ Produksi' => qty($item->producedQty()), '− Terjual' => qty($item->soldQty()), 'Model motor' => $item->model_motor, 'Finishing' => $item->finishing, 'Tipe' => $item->variant, 'Ukuran' => $item->size, 'Merek' => $item->brand, 'Catatan' => $item->notes] as $label => $value)
                    <div class="flex justify-between gap-4 px-5 py-2.5">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Formula — {{ $item->formula?->name ?? 'belum ada formula' }}</h2>
                @if ($item->formula)
                    <a href="{{ route('admin.formulas.show', $item->formula) }}" class="link text-xs">Lihat formula</a>
                @endif
            </div>
            <div class="p-5 text-sm text-slate-600">
                @if ($item->formula)
                    <p>HPP perkiraan dari formula: <strong class="text-slate-900">{{ rupiah($item->formula->estimatedHpp()) }}</strong> per unit.</p>
                    <p class="mt-1">HPP di atas adalah rata-rata dari produksi yang sudah dibukukan.</p>
                @else
                    <p>Produk ini belum dihubungkan ke formula, jadi belum bisa diproduksi. Buat atau ubah formula di menu <a href="{{ route('admin.formulas.index') }}" class="link">Formula Knalpot</a>.</p>
                @endif
            </div>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('admin.items.adjust', $item) }}" class="card space-y-4 p-5">
            @csrf
            <div>
                <h2 class="card-title">Stok opname / penyesuaian</h2>
                <p class="text-[12px] text-muted">Isi jumlah hasil hitung fisik. Stok sistem sekarang {{ qty($item->stock) }} {{ $item->unit }}.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-admin.field name="actual_qty" label="Stok fisik" type="number" step="any" min="0" :value="(float) $item->stock" required />
                <x-admin.field name="adjusted_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            </div>
            <x-admin.select name="reason" label="Alasan" :options="$reasons" value="opname" :placeholder="false" required />
            <x-admin.field name="notes" label="Catatan" placeholder="mis. 1 unit penyok saat kirim" />
            <button class="btn btn-primary w-full">Simpan penyesuaian</button>
        </form>

        <div class="card overflow-hidden xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Riwayat penyesuaian</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Alasan</th><th class="num">Sistem</th><th class="num">Fisik</th><th class="num">Selisih</th><th class="num">Nilai</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="font-mono text-xs">{{ $adjustment->adjustment_number }}</td>
                            <td class="whitespace-nowrap">{{ $adjustment->adjusted_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $adjustment->reasonLabel() }}@if ($adjustment->notes)<p class="text-[11px] text-muted">{{ $adjustment->notes }}</p>@endif</td>
                            <td class="num">{{ qty($adjustment->system_qty) }}</td>
                            <td class="num">{{ qty($adjustment->actual_qty) }}</td>
                            <td class="num font-semibold {{ $adjustment->difference < 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ ($adjustment->difference > 0 ? '+' : '').qty($adjustment->difference) }}</td>
                            <td class="num {{ $adjustment->value() < 0 ? 'text-rose-600' : '' }}">{{ rupiah($adjustment->value()) }}</td>
                            <td class="text-right"><x-admin.delete-button :action="route('admin.items.adjustments.destroy', $adjustment)" confirm="Hapus penyesuaian ini? Stok dikembalikan seperti sebelumnya." /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-8 text-center text-muted">Belum pernah disesuaikan.</td></tr>
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

    @if ($movements->isEmpty())
        <div class="flex justify-end">
            <x-admin.delete-button :action="route('admin.items.destroy', $item)" label="Hapus produk" />
        </div>
    @endif
@endsection
