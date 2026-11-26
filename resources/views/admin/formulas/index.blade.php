@extends('layouts.admin', ['title' => 'Formula Knalpot', 'subtitle' => 'Satu formula = satu jenis knalpot, mis. Mio · Racing Sarangan Tengah. Isinya bagian (Leheran, Silincer), komponen, dan bahan per komponen, dengan HPP yang dihitung dari harga bahan.'])

@section('actions')
    <a href="{{ route('admin.formulas.create', ['jenis' => 'setengah-jadi']) }}" class="btn">+ Formula setengah jadi</a>
    <a href="{{ route('admin.formulas.create') }}" class="btn btn-primary">+ Formula knalpot</a>
@endsection

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama, kode, atau type motor">
        </div>
        <x-admin.select name="kategori" label="Kategori motor" :options="\App\Models\Formula::MOTOR_CATEGORIES" :value="request('kategori')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Kode</th>
                    <th>Formula</th>
                    <th>Motor</th>
                    <th>Bagian & komponen</th>
                    <th class="num">HPP perkiraan</th>
                    <th class="num">Stok hasil</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($formulas as $formula)
                    <tr @class(['opacity-50' => ! $formula->is_active])>
                        <td class="font-mono text-xs whitespace-nowrap text-slate-500">{{ $formula->code }}</td>
                        <td class="min-w-56">
                            <a href="{{ route('admin.formulas.show', $formula) }}" class="link font-medium">{{ $formula->name }}</a>
                            @if ($formula->isSemiFinished())<p class="text-xs text-violet-700">Setengah jadi → {{ $formula->outputMaterial?->label() }}</p>@elseif ($formula->variant)<p class="text-xs text-slate-500">{{ $formula->variant }}</p>@endif
                        </td>
                        <td class="text-xs whitespace-nowrap">{{ $formula->motorCategoryLabel() }}{{ $formula->motor_type ? ' · '.$formula->motor_type : '' }}</td>
                        <td class="min-w-56 text-xs text-slate-600">
                            @forelse ($formula->parts as $part)
                                <p><strong class="font-medium text-slate-800">{{ $part->name }}</strong>: {{ $part->components->pluck('name')->implode(', ') ?: '—' }}</p>
                            @empty
                                <span class="text-amber-600">Belum diisi</span>
                            @endforelse
                        </td>
                        <td class="num font-semibold whitespace-nowrap">{{ rupiah($formula->estimatedHpp()) }}</td>
                        <td class="num">{{ qty($formula->isSemiFinished() ? ($formula->outputMaterial?->stock ?? 0) : ($formula->item?->stock ?? 0)) }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.productions.create', ['formula' => $formula->id]) }}" class="btn btn-sm">Produksi</a>
                            <a href="{{ route('admin.formulas.edit', $formula) }}" class="btn btn-sm">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-slate-500">
                        Belum ada formula. <a href="{{ route('admin.formulas.create') }}" class="link">Buat formula pertama</a>
                        — siapkan dulu bahannya di <a href="{{ route('admin.materials.index') }}" class="link">Master Bahan</a>.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
