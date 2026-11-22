@extends('layouts.admin', ['title' => 'Formula (HPP)', 'subtitle' => 'Resep produksi: bahan + kerja + mesin + overhead = HPP per unit. Bahan dipotong otomatis saat produksi dicatat.'])

@section('actions')
    <a href="{{ route('admin.production-settings.edit') }}" class="btn hidden sm:inline-flex">Pengaturan</a>
    <a href="{{ route('admin.formulas.create') }}" class="btn btn-primary">+ Formula</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama atau kode formula">
        </div>
        <x-admin.select name="active" label="Status" :options="['1' => 'Aktif', '0' => 'Nonaktif']" :value="request('active')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Formula</th><th>Type motor</th><th class="num">Produk</th><th class="num">Hasil</th>
                    <th class="num">Bahan</th><th class="num">Proses</th><th class="num">Waktu</th>
                    <th class="num">HPP / unit</th><th class="num">Harga jual</th><th class="num">Bisa dibuat</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($formulas as $formula)
                    @php
                        $hpp = $formula->hppPerUnit();
                        $minutes = $formula->totalMinutes();
                        $capacity = $formula->capacity();
                    @endphp
                    <tr @class(['opacity-50' => ! $formula->is_active])>
                        <td class="min-w-48">
                            <a href="{{ route('admin.formulas.edit', $formula) }}" class="link">{{ $formula->name }}</a>
                            <p class="font-mono text-[11px] text-slate-400">{{ $formula->code }}</p>
                        </td>
                        <td class="text-xs">{{ $formula->motorcycleModel?->name ?? '—' }}</td>
                        <td class="num">{{ $formula->items_count }}</td>
                        <td class="num whitespace-nowrap">{{ qty($formula->output_qty) }} {{ $formula->output_unit }}</td>
                        <td class="num">{{ $formula->materials_count }}</td>
                        <td class="num">{{ $formula->costs_count + $formula->machines_count }}</td>
                        <td class="num whitespace-nowrap">{{ $minutes >= 60 ? floor($minutes / 60).'j '.round($minutes - floor($minutes / 60) * 60).'m' : round($minutes).'m' }}</td>
                        <td class="num font-semibold">{{ rupiah($hpp) }}@if ($formula->hpp_brand)<p class="text-[11px] font-normal text-slate-400">merek {{ $formula->hpp_brand }}</p>@endif</td>
                        <td class="num text-emerald-700" title="{{ $mainTier ? $mainTier->name.' · margin '.qty($mainTier->margin_percent, 2).'%' : '' }}">{{ $mainTier ? rupiah($mainTier->price($hpp)) : '—' }}</td>
                        <td class="num whitespace-nowrap">
                            <span @class(['font-semibold', 'text-rose-600' => $capacity['unit'] <= 0, 'text-emerald-700' => $capacity['unit'] > 0])>{{ qty($capacity['unit']) }} {{ $formula->output_unit }}</span>
                            @if ($capacity['pembatas'])<p class="text-[11px] text-slate-400">dibatasi {{ $capacity['pembatas'] }}</p>@endif
                        </td>
                        <td><x-admin.status :value="$formula->is_active ? 'AKTIF' : 'NONAKTIF'" /></td>
                        <td class="text-right"><a href="{{ route('admin.formulas.edit', $formula) }}" class="btn btn-sm">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="py-8 text-center text-slate-500">Belum ada formula.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if ($mainTier)
        <p class="text-xs text-slate-500">Kolom harga jual memakai tingkatan "{{ $mainTier->name }}" (margin {{ qty($mainTier->margin_percent, 2) }}%). Semua tingkatan tampil di tab Hasil tiap formula.</p>
    @endif
@endsection
