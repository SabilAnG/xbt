@extends('layouts.admin', ['title' => 'Merek Produk'])

@section('actions')
    <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">+ Merek</a>
@endsection

@section('content')
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($brands as $brand)
            @php $s = $stats->get($brand->name, ['products' => 0, 'stock' => 0, 'stock_value' => 0, 'sold' => 0, 'revenue' => 0, 'produced' => 0]); @endphp
            <div @class(['card overflow-hidden', 'opacity-60' => ! $brand->is_active])>
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl text-sm font-bold text-white" style="background: {{ $brand->color }}">{{ \Illuminate\Support\Str::limit($brand->code ?: $brand->name, 4, '') }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-semibold text-slate-900">{{ $brand->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $brand->description ?: 'Kode '.($brand->code ?: '—') }}</p>
                    </div>
                    <x-admin.status :value="$brand->is_active ? 'AKTIF' : 'NONAKTIF'" />
                </div>
                <dl class="grid grid-cols-2 gap-px bg-slate-100 text-sm">
                    @foreach ([
                        'Produk' => qty($s['products']),
                        'Sisa stok' => qty($s['stock']).' unit',
                        'Nilai stok (HPP)' => rupiah($s['stock_value']),
                        'Diproduksi' => qty($s['produced']).' unit',
                        'Terjual' => qty($s['sold']).' unit',
                        'Omzet' => rupiah($s['revenue']),
                    ] as $label => $value)
                        <div class="bg-white px-5 py-3">
                            <dt class="text-xs text-slate-500">{{ $label }}</dt>
                            <dd class="mt-0.5 font-semibold text-slate-900 tabular-nums">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="flex items-center justify-between gap-2 px-5 py-3">
                    <a href="{{ route('admin.items.index', ['brand' => $brand->name]) }}" class="btn btn-ghost btn-sm">Lihat produk →</a>
                    <div class="flex gap-2">
                        <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-sm">Ubah</a>
                        @unless ($brand->isInUse())
                            <x-admin.delete-button :action="route('admin.brands.destroy', $brand)" />
                        @endunless
                    </div>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-slate-500 sm:col-span-2 xl:col-span-3">Belum ada merek.</div>
        @endforelse
    </section>

    @if ($unbranded)
        <p class="text-xs text-slate-500">{{ $unbranded }} produk belum punya merek. Produk tanpa merek tidak memotong bahan dari baris resep (sama seperti Excel).</p>
    @endif
@endsection
