@extends('layouts.admin', ['title' => 'Data Komponen'])

@php
    $gradients = ['from-[#fbbf94] to-[#e9643a]', 'from-[#fde39a] to-[#eba50f]', 'from-[#c4eaa9] to-[#46ad37]', 'from-[#fdbac4] to-[#df3f58]', 'from-[#9fe3f5] to-[#2fa9d6]', 'from-[#dccafd] to-[#8b49dc]'];
    $badges = ['knalpot' => 'Otomatis · knalpot', 'setengah_jadi' => 'Otomatis · setengah jadi'];
@endphp

@section('actions')
    <a href="{{ route('admin.formulas.index') }}" class="btn">Formula</a>
@endsection

@section('content')
    <section class="card flex flex-wrap items-center gap-3 p-5 sm:p-6">
        <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#fbbf94] to-[#e9643a] text-white">
            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <h2 class="card-title">Tambah bagian</h2>
            <p class="text-[12px] text-muted">Bagian = kelompok di formula, mis. Leheran, Silincer, Aksesoris.</p>
        </div>
        <form method="POST" action="{{ route('admin.component-options.store') }}" class="flex w-full gap-2 sm:w-auto">
            @csrf
            <input type="hidden" name="kind" value="bagian">
            <input name="name" class="input h-10 flex-1 sm:w-64" placeholder="Nama bagian baru" aria-label="Nama bagian baru" required>
            <button class="btn btn-primary h-10">+ Bagian</button>
        </form>
    </section>

    <section class="grid gap-5 lg:grid-cols-2 2xl:grid-cols-3">
        @forelse ($parts as $part)
            <div class="card flex flex-col p-5 sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-[12px] bg-linear-to-br {{ $gradients[$loop->index % count($gradients)] }} text-[15px] font-bold text-white">{{ $loop->iteration }}</span>
                    <form method="POST" action="{{ route('admin.component-options.update', $part) }}" class="min-w-0 flex-1 space-y-2">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center gap-2">
                            <input name="name" value="{{ $part->name }}" class="h-9 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 text-[15px] font-semibold text-ink hover:border-line focus:border-brand-400 focus:bg-white focus:outline-none" aria-label="Nama bagian {{ $part->name }}">
                            <input name="sort_order" type="number" min="0" value="{{ $part->sort_order }}" class="h-9 w-14 rounded-lg border border-line bg-white px-2 text-center text-[12px] text-ink focus:border-brand-400 focus:outline-none" aria-label="Urutan {{ $part->name }}" title="Urutan">
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1"><select name="default_for" class="input h-8 text-[12px]" aria-label="Bawaan {{ $part->name }}">
                                @foreach ($defaults as $value => $label)<option value="{{ $value }}" @selected((string) $part->default_for === (string) $value)>{{ $label }}</option>@endforeach
                            </select></div>
                            <button class="btn btn-sm h-8">Simpan</button>
                        </div>
                    </form>
                    <x-admin.delete-button :action="route('admin.component-options.destroy', $part)" label="✕" :confirm="'Hapus bagian '.$part->name.' beserta daftar komponennya? Formula yang sudah ada tidak berubah.'" />
                </div>
                @if ($part->default_for)
                    <span class="mt-3 self-start rounded-full bg-brand-50 px-2.5 py-0.5 text-[11px] font-semibold text-brand-700">{{ $badges[$part->default_for] }}</span>
                @endif

                <form method="POST" action="{{ route('admin.component-options.store') }}" class="mt-4 flex gap-2">
                    @csrf
                    <input type="hidden" name="kind" value="komponen">
                    <input type="hidden" name="part" value="{{ $part->name }}">
                    <input name="name" class="input h-9 flex-1 text-[13px]" placeholder="Tambah komponen di {{ $part->name }}" aria-label="Tambah komponen di {{ $part->name }}" required>
                    <button class="btn btn-primary btn-sm h-9">+ Tambah</button>
                </form>

                <ul class="mt-3 space-y-1.5">
                    @forelse ($part->components as $preset)
                        @php $used = $usage[mb_strtolower($part->name).'|'.mb_strtolower($preset->name)] ?? 0; @endphp
                        <li class="flex items-center gap-2 rounded-[11px] bg-white/80 px-2.5 py-1.5 shadow-[0_4px_12px_-10px_rgb(60_66_110/0.5)]">
                            <form method="POST" action="{{ route('admin.component-options.update', $preset) }}" class="flex min-w-0 flex-1 items-center gap-1.5">
                                @csrf
                                @method('PUT')
                                <input name="sort_order" type="number" min="0" value="{{ $preset->sort_order }}" class="h-7 w-11 rounded-md border border-transparent bg-surface px-1 text-center text-[11px] text-muted focus:border-brand-400 focus:outline-none" aria-label="Urutan {{ $preset->name }}" title="Urutan">
                                <input name="name" value="{{ $preset->name }}" class="h-7 min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-1.5 text-[13px] font-medium text-ink hover:border-line focus:border-brand-400 focus:bg-white focus:outline-none" aria-label="Nama {{ $preset->name }}">
                                <button class="btn btn-sm h-7 px-2.5 text-[11px]">Simpan</button>
                            </form>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10.5px] font-medium {{ $used ? 'bg-brand-50 text-brand-700' : 'bg-surface text-muted' }}">{{ $used }} formula</span>
                            <x-admin.delete-button :action="route('admin.component-options.destroy', $preset)" label="✕" :confirm="'Hapus '.$preset->name.' dari daftar? Formula yang sudah ada tidak berubah.'" />
                        </li>
                    @empty
                        <li class="py-5 text-center text-[13px] text-muted">Belum ada komponen.</li>
                    @endforelse
                </ul>
            </div>
        @empty
            <div class="card p-10 text-center text-muted lg:col-span-2">Belum ada bagian. Tambahkan di atas.</div>
        @endforelse
    </section>
@endsection
