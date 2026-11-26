@extends('layouts.admin', ['title' => 'Penyesuaian Stok'])

@section('actions')
    <a href="{{ route('admin.adjustments.create') }}" class="btn btn-primary">+ Penyesuaian</a>
@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Bahan</th><th>Gudang</th><th>Alasan</th><th class="num">Jumlah</th><th class="num">Nilai</th><th></th></tr></thead>
                <tbody>
                @forelse ($adjustments as $adjustment)
                    @php $material = $adjustment->material; $sign = $adjustment->direction === 'in' ? '+' : '−'; @endphp
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $adjustment->adjustment_number }}</td>
                        <td class="whitespace-nowrap">{{ $adjustment->adjusted_at->translatedFormat('d M Y') }}</td>
                        <td><a href="{{ route('admin.materials.show', $material) }}" class="link">{{ $material->label() }}</a></td>
                        <td class="text-xs">{{ $adjustment->warehouse->typeLabel() }}</td>
                        <td class="text-sm">{{ $adjustment->reasonLabel() }}@if ($adjustment->notes)<p class="text-xs text-slate-500">{{ $adjustment->notes }}</p>@endif</td>
                        <td class="num font-semibold whitespace-nowrap {{ $adjustment->direction === 'in' ? 'text-emerald-600' : 'text-rose-600' }}">
                            @if ($material->isPipe() && $adjustment->direction === 'in')
                                {{ $sign }}{{ qty($adjustment->qty) }} × {{ qty($adjustment->length_cm, 2) }} cm
                            @elseif ($material->isPipe())
                                {{ $sign }}{{ qty($adjustment->length_cm, 2) }} cm <span class="font-mono text-xs font-normal">{{ $adjustment->piece?->code }}</span>
                            @else
                                {{ $sign }}{{ qty($adjustment->qty) }} {{ $material->stockUnit() }}
                            @endif
                        </td>
                        <td class="num">{{ rupiah((float) $adjustment->unit_cost * ($material->isPipe() && $adjustment->direction === 'in' ? (float) $adjustment->qty * (float) $adjustment->length_cm : (float) ($material->isPipe() ? $adjustment->length_cm : $adjustment->qty))) }}</td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.adjustments.destroy', $adjustment)" label="Hapus" confirm="Hapus penyesuaian ini? Stoknya ikut dikembalikan." /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">Belum ada penyesuaian. Mulai dengan <a href="{{ route('admin.adjustments.create') }}" class="link">mengisi stok awal</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($adjustments->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $adjustments->links() }}</div>@endif
    </div>
@endsection
