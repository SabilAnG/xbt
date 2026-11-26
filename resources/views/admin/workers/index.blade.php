@extends('layouts.admin', ['title' => 'Pekerja'])

@php $statusTone = ['hadir' => 'bg-emerald-50 text-emerald-700', 'setengah' => 'bg-amber-50 text-amber-700', 'izin' => 'bg-sky-50 text-sky-700', 'sakit' => 'bg-violet-50 text-violet-700', 'alpa' => 'bg-rose-50 text-rose-600', 'libur' => 'bg-surface text-muted']; @endphp

@section('actions')
    <a href="{{ route('admin.attendances.index') }}" class="btn">Absensi hari ini</a>
    <a href="{{ route('admin.workers.create') }}" class="btn btn-primary">+ Pekerja</a>
@endsection

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Pekerja aktif" :value="$workers->where('is_active', true)->count().' orang'" />
        <x-admin.stat label="Hadir hari ini" :value="$today->filter(fn ($s) => in_array($s, ['hadir', 'setengah'], true))->count().' orang'" :href="route('admin.attendances.index')" />
        <x-admin.stat label="Upah harian total" :value="rupiah($workers->where('is_active', true)->sum('daily_wage'))" />
        <x-admin.stat label="Sisa kasbon" :value="rupiah($balances->sum())" trend="down" />
    </section>

    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama atau jabatan">
        </div>
        <x-admin.select name="status" label="Tampilkan" :options="['semua' => 'Termasuk nonaktif']" :value="request('status')" placeholder="Aktif saja" />
    </x-admin.filters>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($workers as $worker)
            @php $status = $today[$worker->id] ?? null; @endphp
            <a href="{{ route('admin.workers.show', $worker) }}" @class(['card flex flex-col gap-4 p-5 transition hover:-translate-y-0.5', 'opacity-60' => ! $worker->is_active])>
                <div class="flex items-center gap-3">
                    <span class="grid size-12 shrink-0 place-items-center rounded-[14px] bg-linear-to-br from-[#ffb36b] to-[#d9573a] text-[15px] font-bold text-white shadow-(--shadow-soft)">
                        {{ strtoupper(collect(explode(' ', $worker->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[15px] font-semibold text-ink">{{ $worker->name }}</p>
                        <p class="truncate text-[12px] text-muted">{{ $worker->position ?: 'Pekerja' }}{{ $worker->is_active ? '' : ' · nonaktif' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $status ? $statusTone[$status] : 'bg-surface text-muted' }}">
                        {{ $status ? \App\Models\Attendance::STATUSES[$status][0] : 'Belum absen' }}
                    </span>
                </div>
                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-[12px] bg-white/80 px-2 py-2"><dt class="text-[10.5px] text-muted">Upah / hari</dt><dd class="text-[13px] font-semibold text-ink">{{ rupiah($worker->daily_wage) }}</dd></div>
                    <div class="rounded-[12px] bg-white/80 px-2 py-2"><dt class="text-[10.5px] text-muted">Makan / hari</dt><dd class="text-[13px] font-semibold text-ink">{{ rupiah($worker->meal_allowance) }}</dd></div>
                    <div class="rounded-[12px] bg-white/80 px-2 py-2"><dt class="text-[10.5px] text-muted">Kasbon</dt><dd class="text-[13px] font-semibold {{ $balances[$worker->id] > 0 ? 'text-rose-600' : 'text-ink' }}">{{ rupiah($balances[$worker->id]) }}</dd></div>
                </dl>
            </a>
        @empty
            <div class="card p-10 text-center text-muted sm:col-span-2 xl:col-span-3">Belum ada pekerja. <a href="{{ route('admin.workers.create') }}" class="link">Tambah pekerja</a>.</div>
        @endforelse
    </section>
@endsection
