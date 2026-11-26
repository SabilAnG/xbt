@extends('layouts.admin', ['title' => 'Rencana Produksi'])

@section('actions')
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">+ Rencana</a>
@endsection

@section('content')
    <x-admin.filters>
        <x-admin.select name="status" label="Status" :options="\App\Models\ProductionPlan::STATUSES" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Tanggal</th><th>Judul</th><th>Formula</th><th class="num">Unit</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.plans.show', $plan) }}" class="link">{{ $plan->plan_number }}</a></td>
                        <td class="whitespace-nowrap">{{ $plan->planned_for->translatedFormat('d M Y') }}</td>
                        <td>{{ $plan->title ?? '—' }}</td>
                        <td class="text-xs text-slate-600">{{ $plan->lines->map(fn ($line) => $line->formula->name.' × '.qty($line->qty))->implode(' · ') }}</td>
                        <td class="num font-semibold">{{ qty($plan->totalUnits()) }}</td>
                        <td><x-admin.status :value="$plan->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-slate-500">Belum ada rencana. <a href="{{ route('admin.plans.create') }}" class="link">Buat rencana produksi</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($plans->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $plans->links() }}</div>@endif
    </div>
@endsection
