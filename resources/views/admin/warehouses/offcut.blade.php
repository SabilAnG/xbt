@extends('layouts.admin', ['title' => $warehouse->name])

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn">Semua gudang</a>
    <a href="{{ route('admin.adjustments.create') }}" class="btn btn-primary">Catat / afkir potongan</a>
@endsection

@section('content')
    @php $all = $pieces->flatten(); @endphp
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Potongan tersedia" :value="$all->count()" />
        <x-admin.stat label="Total panjang" :value="qty($all->sum('length_cm'), 2).' cm'" />
        <x-admin.stat label="Nilai" :value="rupiah($all->sum(fn ($piece) => $piece->value()))" tone="brand" />
    </section>

    @forelse ($pieces as $group)
        @php $material = $group->first()->material; @endphp
        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title"><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->label() }}</a></h2>
                <span class="text-xs text-slate-500">{{ $group->count() }} potong · {{ qty($group->sum('length_cm'), 2) }} cm</span>
            </div>
            <div class="flex flex-wrap gap-2 p-5">
                @foreach ($group as $piece)
                    <div class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <p class="font-semibold tabular-nums">{{ qty($piece->length_cm, 2) }} cm</p>
                        <p class="font-mono text-[11px] text-slate-400">{{ $piece->code }} · {{ rupiah($piece->value()) }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="card p-10 text-center text-slate-500">Belum ada potongan sisa. Potongan muncul otomatis setelah produksi dibukukan.</div>
    @endforelse

    @if ($scrap->isNotEmpty())
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Afkir terakhir</h2><span class="text-xs text-slate-500">Terlalu pendek untuk dipakai lagi</span></div>
            <table class="table">
                <tbody>
                @foreach ($scrap as $piece)
                    <tr>
                        <td class="font-mono text-xs">{{ $piece->code }}</td>
                        <td>{{ $piece->material->label() }}</td>
                        <td class="num">{{ qty($piece->length_cm, 2) }} cm</td>
                        <td class="num text-slate-500">{{ rupiah($piece->value()) }}</td>
                        <td class="text-xs text-slate-400">{{ $piece->updated_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
