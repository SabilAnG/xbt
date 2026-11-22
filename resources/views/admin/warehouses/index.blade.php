@extends('layouts.admin', ['title' => 'Gudang', 'subtitle' => 'Alur barang: bahan baku dibuat jadi komponen (setengah jadi), komponen dirakit jadi knalpot (finish good).'])

@php
    $meta = [
        'bahan_baku' => ['icon' => 'layers', 'tone' => 'bg-sky-50 text-sky-600', 'unit' => 'bahan', 'source' => 'Stok Bahan'],
        'setengah_jadi' => ['icon' => 'puzzle', 'tone' => 'bg-amber-50 text-amber-600', 'unit' => 'komponen', 'source' => 'Master Komponen'],
        'finish_good' => ['icon' => 'box', 'tone' => 'bg-emerald-50 text-emerald-600', 'unit' => 'produk', 'source' => 'Stok Produk'],
    ];
@endphp

@section('content')
    <section class="grid gap-4 lg:grid-cols-3">
        @foreach ($summaries as $summary)
            @php $warehouse = $summary['warehouse']; $m = $meta[$warehouse->type]; @endphp
            <a href="{{ route('admin.warehouses.show', $warehouse->slug()) }}" class="card group relative block p-5 transition hover:border-slate-300 hover:shadow-md">
                <div class="flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $m['tone'] }}">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon($m['icon']) }}"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] font-semibold tracking-wide text-slate-400 uppercase">{{ $loop->iteration }} · {{ $warehouse->typeLabel() }}</p>
                        <p class="truncate text-[15px] font-semibold text-slate-900">{{ $warehouse->name }}</p>
                        @if ($warehouse->description)<p class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ $warehouse->description }}</p>@endif
                    </div>
                </div>
                <p class="mt-5 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums">{{ rupiah($summary['value']) }}</p>
                <p class="text-xs text-slate-500">Nilai stok</p>
                <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center">
                    <div><dt class="text-[11px] text-slate-500">Jenis {{ $m['unit'] }}</dt><dd class="text-sm font-semibold text-slate-900 tabular-nums">{{ $summary['count'] }}</dd></div>
                    <div><dt class="text-[11px] text-slate-500">Total qty</dt><dd class="text-sm font-semibold text-slate-900 tabular-nums">{{ qty(round($summary['qty'], 2)) }}</dd></div>
                    <div><dt class="text-[11px] text-slate-500">Habis/menipis</dt><dd @class(['text-sm font-semibold tabular-nums', 'text-rose-600' => $summary['low'] > 0, 'text-slate-900' => $summary['low'] === 0])>{{ $summary['low'] }}</dd></div>
                </dl>
                @unless ($loop->last)
                    <span class="absolute top-1/2 -right-3.5 z-10 hidden size-7 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-slate-400 lg:grid">→</span>
                @endunless
            </a>
        @endforeach
    </section>

    <section class="card p-5 text-sm text-slate-600">
        <h2 class="card-title mb-2">Cara kerja</h2>
        <ol class="list-decimal space-y-1 pl-5">
            <li><strong>Bahan Baku</strong> bertambah dari Pembelian Bahan dan berkurang saat dipakai produksi atau dibuat jadi komponen.</li>
            <li><strong>Setengah Jadi</strong> berisi komponen dari <a href="{{ route('admin.components.index') }}" class="link">Master Komponen</a>. Catat hasil dibuat; centang "potong bahan baku" agar stok bahan ikut berkurang sesuai bahan penyusunnya.</li>
            <li><strong>Finish Good</strong> adalah knalpot jadi dari Stok Produk: bertambah dari Produksi, berkurang dari Penjualan.</li>
        </ol>
    </section>
@endsection
