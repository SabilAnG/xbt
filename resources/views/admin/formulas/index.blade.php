@extends('layouts.admin', ['title' => 'Resep BOM', 'subtitle' => 'Bahan per satu knalpot. Dipakai otomatis saat produksi dicatat.'])

@section('actions')
    <a href="{{ route('admin.formulas.create') }}" class="btn btn-primary">+ Resep</a>
@endsection

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($formulas as $formula)
            <a href="{{ route('admin.formulas.edit', $formula) }}" class="card block p-5 transition hover:border-brand-500/50 hover:shadow">
                <p class="text-xs font-mono text-zinc-400">{{ $formula->code }}</p>
                <h2 class="mt-1 text-base font-semibold text-zinc-900">{{ $formula->name }}</h2>
                <div class="mt-4 flex gap-6 text-sm">
                    <div><p class="text-xs text-zinc-500">Baris bahan</p><p class="font-semibold tabular-nums">{{ $formula->materials_count }}</p></div>
                    <div><p class="text-xs text-zinc-500">Dipakai produk</p><p class="font-semibold tabular-nums">{{ $formula->items_count }}</p></div>
                </div>
            </a>
        @empty
            <div class="card p-8 text-center text-sm text-zinc-500 md:col-span-2 xl:col-span-3">Belum ada resep.</div>
        @endforelse
    </div>
@endsection
