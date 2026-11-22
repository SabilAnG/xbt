@extends('layouts.admin', ['title' => 'Daftar Pilihan', 'subtitle' => 'Padanan sheet "Daftar": pilihan baru langsung muncul di form produk, penjualan, dan pengeluaran'])

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($lists as $list => $label)
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ $label }}</h2></div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($options->get($list, collect()) as $option)
                        <li class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                            <span>{{ $option->value }}</span>
                            <x-admin.delete-button :action="route('admin.list-options.destroy', $option)" label="×" :confirm="'Hapus pilihan '.$option->value.'?'" />
                        </li>
                    @empty
                        <li class="px-5 py-3 text-sm text-slate-500">Belum ada pilihan.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('admin.list-options.store') }}" class="flex gap-2 border-t border-slate-100 p-4">
                    @csrf
                    <input type="hidden" name="list" value="{{ $list }}">
                    <input name="value" class="input" placeholder="Tambah {{ strtolower($label) }}" required>
                    <button class="btn btn-sm shrink-0">Tambah</button>
                </form>
            </section>
        @endforeach

        <section class="card">
            <div class="card-header"><h2 class="card-title">Kategori Pengeluaran</h2></div>
            <ul class="divide-y divide-slate-100">
                @foreach ($categories as $category)
                    <li class="flex items-center justify-between gap-3 px-5 py-2 text-sm">
                        <span>{{ $category->name }} <span class="text-xs text-slate-400">· {{ $category->expenses_count }} transaksi</span></span>
                        @if ($category->expenses_count === 0)
                            <x-admin.delete-button :action="route('admin.expense-categories.destroy', $category)" label="×" :confirm="'Hapus kategori '.$category->name.'?'" />
                        @endif
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.expense-categories.store') }}" class="flex gap-2 border-t border-slate-100 p-4">
                @csrf
                <input name="name" class="input" placeholder="Tambah kategori" required>
                <button class="btn btn-sm shrink-0">Tambah</button>
            </form>
        </section>

        <section class="card p-5 text-sm text-slate-600">
            <h2 class="card-title mb-2">Pilihan lain di sheet Daftar</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li><strong>Resep BOM</strong> dan <strong>Merek BOM</strong> → menu <a href="{{ route('admin.formulas.index') }}" class="link">Resep BOM</a></li>
                <li><strong>Data host</strong> & <strong>data tukang</strong> → menu <a href="{{ route('admin.hosts.index') }}" class="link">Data Host &amp; Tukang</a></li>
                <li><strong>Status transfer</strong>: Belum / Sudah (tetap)</li>
            </ul>
        </section>
    </div>
    @error('value')<p class="error">{{ $message }}</p>@enderror
    @error('name')<p class="error">{{ $message }}</p>@enderror
@endsection
