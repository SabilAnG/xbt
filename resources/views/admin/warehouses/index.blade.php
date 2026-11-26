@extends('layouts.admin', ['title' => 'Gudang', 'subtitle' => 'Alur barang: Bahan Baku & Setengah Jadi (dari pembelian) → produksi → sisa pipa ke Stok Sisa Produksi → knalpot jadi ke Finish Good.'])

@section('actions')
    <a href="{{ route('admin.adjustments.create') }}" class="btn">Penyesuaian stok</a>
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian</a>
@endsection

@section('content')
    <section class="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
        @foreach ($cards as $card)
            <a href="{{ route('admin.warehouses.show', $card['warehouse']->slug()) }}" class="card group flex flex-col gap-2 p-5 transition hover:-translate-y-px hover:shadow-md">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $card['warehouse']->code }}</p>
                    <span class="text-slate-300 group-hover:text-brand-600">→</span>
                </div>
                <h2 class="text-base font-semibold text-slate-900">{{ $card['warehouse']->name }}</h2>
                <p class="text-xs text-slate-500">{{ $card['warehouse']->typeDescription() }}</p>
                <p class="mt-auto pt-2 text-xl font-semibold tabular-nums">{{ rupiah($card['value']) }}</p>
                <p class="text-xs text-slate-500">
                    {{ $card['count'] }} {{ $card['unit'] }}
                    @if ($card['qty'] !== null) · {{ qty($card['qty']) }} {{ ['finish_good' => 'pcs', 'di_vendor' => 'unit di vendor'][$card['warehouse']->type] ?? 'potongan' }} @endif
                </p>
            </a>
        @endforeach
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Pergerakan terakhir</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Gudang</th><th>Bahan</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th></tr></thead>
                <tbody>
                @forelse ($movements as $movement)
                    <tr>
                        <td class="whitespace-nowrap">{{ $movement->moved_at->translatedFormat('d M Y') }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $movement->warehouse->typeLabel() }}</td>
                        <td><a href="{{ route('admin.materials.show', $movement->material) }}" class="link">{{ $movement->material->label() }}</a></td>
                        <td class="text-xs">{{ $movement->typeLabel() }}</td>
                        <td class="text-xs text-slate-500">{{ $movement->notes }}</td>
                        <td class="num text-emerald-600">{{ (float) $movement->qty_in ? '+'.qty($movement->qty_in, 2).' '.$movement->material->stockUnit() : '' }}</td>
                        <td class="num text-rose-600">{{ (float) $movement->qty_out ? '−'.qty($movement->qty_out, 2).' '.$movement->material->stockUnit() : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">Gudang masih kosong. Isi lewat <a href="{{ route('admin.purchases.create') }}" class="link">pembelian</a> atau <a href="{{ route('admin.adjustments.create') }}" class="link">stok awal</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
