@extends('layouts.admin', ['title' => $formula->name, 'subtitle' => $formula->code.' · '.($formula->isSemiFinished() ? 'Barang setengah jadi' : $formula->motorCategoryLabel()).($formula->motor_type ? ' · '.$formula->motor_type : '').($formula->variant ? ' · '.$formula->variant : '')])

@section('actions')
    <form method="POST" action="{{ route('admin.formulas.duplicate', $formula) }}" class="hidden sm:block">@csrf<button class="btn">Salin</button></form>
    <a href="{{ route('admin.plans.create', ['formula' => $formula->id]) }}" class="btn hidden sm:inline-flex">Rencanakan</a>
    <a href="{{ route('admin.productions.create', ['formula' => $formula->id]) }}" class="btn">Produksi</a>
    <a href="{{ route('admin.formulas.edit', $formula) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    @php
        $byPart = $requirements->groupBy('part');
        $material = (float) $requirements->sum('cost');
        $other = $formula->otherCostPerUnit();
    @endphp

    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat :label="$formula->isSemiFinished() ? 'HPP perkiraan (tanpa jasa vendor)' : 'HPP perkiraan'" :value="rupiah($material + $other)" tone="brand" />
        <x-admin.stat label="Biaya bahan" :value="rupiah($material)" />
        <x-admin.stat label="Ongkos" :value="rupiah($other)" />
        @if ($formula->isSemiFinished())
            <x-admin.stat label="Hasil (Setengah Jadi)" :value="qty($formula->outputMaterial?->stock ?? 0).' pcs'" :hint="$formula->outputMaterial?->label()" :href="$formula->outputMaterial ? route('admin.materials.show', $formula->outputMaterial) : null" />
        @else
            <x-admin.stat label="Produk jadi" :value="qty($formula->item?->stock ?? 0).' pcs'" :hint="$formula->item?->name" :href="$formula->item ? route('admin.items.show', $formula->item) : null" />
        @endif
    </section>

    @foreach ($formula->parts as $part)
        <section class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">{{ $part->name }}</h2>
                <span class="text-sm text-slate-500">Subtotal <strong class="text-slate-900">{{ rupiah($byPart->get($part->name, collect())->sum('cost')) }}</strong></span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Komponen</th><th>Bahan</th><th>Gudang</th><th class="num">Kebutuhan</th><th class="num">Harga</th><th class="num">Biaya / knalpot</th></tr></thead>
                    <tbody>
                    @forelse ($part->components as $component)
                        @forelse ($component->materials as $line)
                            <tr>
                                @if ($loop->first)
                                    <td rowspan="{{ $component->materials->count() }}" class="align-top font-medium">
                                        {{ $component->name }}
                                        @if ((float) $component->qty !== 1.0)<span class="text-xs text-slate-500">× {{ qty($component->qty) }}</span>@endif
                                    </td>
                                @endif
                                <td><a href="{{ route('admin.materials.show', $line->material) }}" class="link">{{ $line->material->label() }}</a></td>
                                <td class="text-xs whitespace-nowrap">{{ $line->material->warehouse->typeLabel() }}</td>
                                <td class="num whitespace-nowrap">{{ qty($line->qty, 2) }} {{ $line->material->stockUnit() }}{{ (float) $component->qty !== 1.0 ? ' × '.qty($component->qty) : '' }}</td>
                                <td class="num whitespace-nowrap text-slate-500">{{ rupiah($line->material->unitCost()) }}/{{ $line->material->stockUnit() }}</td>
                                <td class="num font-semibold">{{ rupiah((float) $line->qty * (float) $component->qty * $line->material->unitCost()) }}</td>
                            </tr>
                        @empty
                            <tr><td class="font-medium">{{ $component->name }}</td><td colspan="5" class="text-amber-600">Belum ada bahan.</td></tr>
                        @endforelse
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-500">Belum ada komponen.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Ongkos per knalpot</h2></div>
            <table class="table">
                <tbody>
                @forelse ($formula->costs as $cost)
                    <tr><td>{{ $cost->name }}</td><td class="num">{{ rupiah($cost->amount) }}</td></tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Belum ada ongkos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Kebutuhan pipa per knalpot</h2></div>
            <table class="table">
                <tbody>
                @forelse ($requirements->filter(fn ($line) => $line['material']->isPipe())->groupBy(fn ($line) => $line['material']->id) as $lines)
                    <tr>
                        <td>{{ $lines->first()['material']->label() }}</td>
                        <td class="text-xs text-slate-500">{{ $lines->map(fn ($line) => $line['component'].' '.qty($line['qty'], 2).' cm')->implode(' · ') }}</td>
                        <td class="num font-semibold whitespace-nowrap">{{ qty($lines->sum('qty'), 2) }} cm</td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-500">Formula ini tidak memakai pipa.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($formula->notes)
        <section class="card p-5 text-sm whitespace-pre-line text-slate-600">{{ $formula->notes }}</section>
    @endif
@endsection
