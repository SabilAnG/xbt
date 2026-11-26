@extends('layouts.admin', ['title' => 'Produksi'])

@section('actions')
    <a href="{{ route('admin.productions.create') }}" class="btn btn-primary">+ Produksi</a>
@endsection

@section('content')
    <x-admin.filters>
        <x-admin.select name="status" label="Status" :options="['draft' => 'Draf', 'posted' => 'Dibukukan']" :value="request('status')" placeholder="Semua" />
        <x-admin.select name="mode" label="Dikerjakan" :options="['internal' => 'Sendiri', 'vendor' => 'Vendor']" :value="request('mode')" placeholder="Semua" />
        @if (request('rencana'))<input type="hidden" name="rencana" value="{{ request('rencana') }}">@endif
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Formula</th><th class="num">Unit</th><th>Tukang</th><th class="num">HPP / unit</th><th class="num">Total biaya</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($productions as $production)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap">
                            <a href="{{ route('admin.productions.show', $production) }}" class="link">{{ $production->production_number }}</a>
                            @if ($production->planLine)<p class="text-[11px] text-slate-400">{{ $production->planLine->plan->plan_number }}</p>@endif
                        </td>
                        <td class="whitespace-nowrap">{{ $production->produced_at->translatedFormat('d M Y') }}</td>
                        <td class="min-w-48">{{ $production->formula->name }}@if ($production->isVendor())<p class="text-xs text-violet-700">Vendor: {{ $production->vendor }}{{ $production->process ? ' · '.$production->process : '' }}</p>@endif</td>
                        <td class="num font-semibold">{{ qty($production->qty) }}</td>
                        <td class="text-sm">{{ $production->worker ?? '—' }}</td>
                        <td class="num">{{ $production->isPosted() ? rupiah($production->hpp_per_unit) : '—' }}</td>
                        <td class="num">{{ $production->isPosted() ? rupiah($production->total_cost) : '—' }}</td>
                        <td><x-admin.status :value="$production->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-10 text-center text-slate-500">Belum ada produksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($productions->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $productions->links() }}</div>@endif
    </div>
@endsection
