@extends('layouts.admin', ['title' => 'Penyesuaian Bahan', 'subtitle' => 'Bahan rusak/hilang (minus) atau koreksi hasil hitung gudang (plus/minus)'])

@section('actions')
    <a href="{{ route('admin.adjustments.create') }}" class="btn btn-primary">+ Penyesuaian</a>
@endsection

@section('content')
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Bahan</th><th class="num">Qty (±)</th><th>Alasan</th><th class="num">Saldo setelah</th><th></th></tr></thead>
                <tbody>
                @forelse ($adjustments as $adjustment)
                    @php $delta = (float) $adjustment->qty_in - (float) $adjustment->qty_out; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $adjustment->moved_at->translatedFormat('d M Y') }}</td>
                        <td><a href="{{ route('admin.materials.show', $adjustment->material_id) }}" class="link">{{ $adjustment->material?->name }}</a></td>
                        <td class="num font-semibold {{ $delta < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $delta > 0 ? '+' : '' }}{{ qty($delta) }}</td>
                        <td>{{ $adjustment->notes }}</td>
                        <td class="num">{{ qty($adjustment->balance_after) }}</td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.adjustments.destroy', $adjustment)" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-zinc-500">Belum ada penyesuaian.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $adjustments->links() }}
@endsection
