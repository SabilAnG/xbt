@extends('layouts.admin', ['title' => 'Data Motor'])

@php
    $styles = [
        'kategori' => ['from-[#fbbf94] to-[#e9643a]', 'layers'],
        'brand' => ['from-[#fdbac4] to-[#df3f58]', 'tag'],
        'type' => ['from-[#fde39a] to-[#eba50f]', 'bike'],
        'varian' => ['from-[#c4eaa9] to-[#46ad37]', 'puzzle'],
    ];
@endphp

@section('content')
    <section class="grid gap-5 lg:grid-cols-2">
        @foreach (\App\Models\MotorOption::KINDS as $kind => $label)
            @php
                [$gradient, $icon] = $styles[$kind];
                $rows = $options->get($kind, collect());
                if ($kind === 'type') {
                    $rows = $rows->sortBy(fn ($row) => [$row->parent?->name ?? 'zzz', $row->sort_order, $row->name])->values();
                }
            @endphp
            <div class="card flex flex-col p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br {{ $gradient }} text-white">
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon($icon) }}"/></svg>
                    </span>
                    <h2 class="card-title flex-1">{{ $label }}</h2>
                    <span class="rounded-full bg-white px-3 py-1 text-[12px] font-semibold text-ink shadow-(--shadow-soft)">{{ $rows->count() }}</span>
                </div>

                <form method="POST" action="{{ route('admin.motor-options.store') }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <input type="hidden" name="kind" value="{{ $kind }}">
                    <input name="name" class="input h-10 min-w-40 flex-1" placeholder="Tambah {{ strtolower($label) }}..." aria-label="Tambah {{ strtolower($label) }}" required>
                    @if ($kind === 'type')
                        <div class="w-40"><select name="brand" class="input h-10" aria-label="Brand type baru"><option value="">— brand —</option>@foreach ($brands as $brand)<option value="{{ $brand }}">{{ $brand }}</option>@endforeach</select></div>
                    @endif
                    <button class="btn btn-primary h-10">+ Tambah</button>
                </form>

                <ul class="mt-4 space-y-2">
                    @forelse ($rows as $option)
                        @php $used = (int) ($usage[$kind][$option->name] ?? 0); @endphp
                        <li class="flex items-center gap-2 rounded-[12px] bg-white/80 px-3 py-2 shadow-[0_4px_12px_-10px_rgb(60_66_110/0.5)]">
                            <form method="POST" action="{{ route('admin.motor-options.update', $option) }}" class="flex min-w-0 flex-1 items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input name="name" value="{{ $option->name }}" class="h-8 min-w-0 flex-1 rounded-lg border border-transparent bg-transparent px-2 text-[13px] font-medium text-ink hover:border-line focus:border-brand-400 focus:bg-white focus:outline-none" aria-label="Nama {{ $option->name }}">
                                @if ($kind === 'type')
                                    <div class="w-36 shrink-0"><select name="brand" class="input h-8 text-[12px]" aria-label="Brand {{ $option->name }}"><option value="">— tanpa brand —</option>@foreach ($brands as $brand)<option value="{{ $brand }}" @selected($option->parent?->name === $brand)>{{ $brand }}</option>@endforeach</select></div>
                                @endif
                                <button class="btn btn-sm h-8" title="Simpan nama">Simpan</button>
                            </form>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium {{ $used ? 'bg-brand-50 text-brand-700' : 'bg-surface text-muted' }}">{{ $used }} formula</span>
                            @if ($kind === 'brand' && $option->children_count)
                                <span class="shrink-0 rounded-full bg-accent-soft px-2 py-0.5 text-[11px] font-medium text-accent-strong">{{ $option->children_count }} type</span>
                            @endif
                            @unless ($used || ($kind === 'brand' && $option->children_count))
                                <x-admin.delete-button :action="route('admin.motor-options.destroy', $option)" label="✕" :confirm="'Hapus '.$option->name.'?'" />
                            @endunless
                        </li>
                    @empty
                        <li class="py-6 text-center text-[13px] text-muted">Belum ada data.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </section>
@endsection
