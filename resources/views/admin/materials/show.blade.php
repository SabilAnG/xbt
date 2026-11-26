@extends('layouts.admin', ['title' => $material->label(), 'subtitle' => $material->code.' · '.$material->warehouse?->name.($material->category ? ' · '.$material->category->name : '').($material->conversionLabel() ? ' · '.$material->conversionLabel() : '')])

@section('actions')
    <a href="{{ route('admin.adjustments.create', ['material' => $material->id]) }}" class="btn hidden sm:inline-flex">Penyesuaian</a>
    <a href="{{ route('admin.purchases.create', ['material' => $material->id]) }}" class="btn hidden sm:inline-flex">Beli</a>
    <a href="{{ route('admin.materials.edit', $material) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Stok total" :value="qty($material->stock, 2).' '.$material->stockUnit()" :tone="$material->stockStatus() === 'AMAN' ? 'default' : 'warning'" />
        <x-admin.stat :label="'Harga rata-rata / '.$material->stockUnit()" :value="rupiah($material->unitCost())" />
        <x-admin.stat :label="'Harga beli terakhir / '.$material->purchaseUnit()" :value="rupiah($material->last_price)" />
        <x-admin.stat label="Nilai stok" :value="rupiah($material->stocks->sum('value'))" />
    </section>

    @if ($material->is_producible && $producedBy || $sourcing->isNotEmpty())
        <section class="card p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">Asal barang · {{ $material->sourcingLabel() }}</h2>
                @if ($producedBy)
                    <a href="{{ route('admin.formulas.show', $producedBy) }}" class="link text-sm">Formula {{ $producedBy->name }} · HPP ± {{ rupiah($producedBy->estimatedHpp()) }}</a>
                @endif
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach (['beli' => 'Dari pembelian', 'buat' => 'Dari produksi (internal & vendor)'] as $key => $label)
                    @php $row = $sourcing->get($key); @endphp
                    <div class="rounded-lg border border-slate-200 p-3 text-sm">
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                        @if ($row && (float) $row->qty > 0)
                            <p class="font-semibold">{{ qty($row->qty, 2) }} {{ $material->stockUnit() }} · rata-rata {{ rupiah((float) $row->value / (float) $row->qty) }}</p>
                        @else
                            <p class="text-slate-400">Belum ada</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="grid gap-4 sm:gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Stok per gudang</h2></div>
            <table class="table">
                <tbody>
                @forelse ($material->stocks as $stock)
                    <tr>
                        <td><a href="{{ route('admin.warehouses.show', $stock->warehouse->slug()) }}" class="link">{{ $stock->warehouse->name }}</a></td>
                        <td class="num font-semibold">{{ qty($stock->qty, 2) }} {{ $material->stockUnit() }}</td>
                        <td class="num text-slate-500">{{ rupiah($stock->value) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Belum ada stok.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($material->isPipe())
                <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">
                    1 batang = {{ qty($material->bar_length_cm, 2) }} cm · sisa di bawah {{ qty($material->min_reusable_cm, 2) }} cm diafkir.
                </p>
            @endif
        </div>

        <div class="card overflow-hidden xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Dipakai di formula</h2></div>
            <table class="table">
                <thead><tr><th>Formula</th><th>Bagian · komponen</th><th class="num">Kebutuhan</th></tr></thead>
                <tbody>
                @forelse ($usedIn as $line)
                    <tr>
                        <td><a href="{{ route('admin.formulas.show', $line->component->part->formula) }}" class="link">{{ $line->component->part->formula->name }}</a></td>
                        <td class="text-sm">{{ $line->component->part->name }} · {{ $line->component->name }}</td>
                        <td class="num">{{ qty($line->qty, 2) }} {{ $material->stockUnit() }} × {{ qty($line->component->qty) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-500">Belum dipakai di formula mana pun.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($material->isPipe())
        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Batang & potongan tersedia</h2>
                <span class="text-xs text-slate-500">{{ $pieces->count() }} potong</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Kode</th><th>Gudang</th><th class="num">Panjang</th><th class="num">Awal</th><th class="num">Harga/cm</th><th class="num">Nilai</th></tr></thead>
                    <tbody>
                    @forelse ($pieces as $piece)
                        <tr>
                            <td class="font-mono text-xs">{{ $piece->code }}</td>
                            <td class="text-xs">{{ $piece->warehouse->typeLabel() }}</td>
                            <td class="num font-semibold">{{ qty($piece->length_cm, 2) }} cm</td>
                            <td class="num text-slate-500">{{ qty($piece->initial_length_cm, 2) }} cm</td>
                            <td class="num">{{ rupiah($piece->cost_per_cm) }}</td>
                            <td class="num">{{ rupiah($piece->value()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Tidak ada batang atau potongan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Kartu stok</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Gudang</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo gudang</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $movement->warehouse->typeLabel() }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $movement->typeLabel() }}</td>
                        <td class="text-xs text-slate-600">{{ $movement->notes }}</td>
                        <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in, 2) : '' }}</td>
                        <td class="num text-rose-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out, 2) : '' }}</td>
                        <td class="num font-semibold">{{ qty($movement->balance_after, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada pergerakan stok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($movements->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $movements->links() }}</div>@endif
    </section>
@endsection
