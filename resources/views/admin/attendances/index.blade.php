@extends('layouts.admin', ['title' => 'Absensi'])

@php
    $statuses = \App\Models\Attendance::STATUSES;
    $tone = [
        'hadir' => 'peer-checked:bg-linear-to-b peer-checked:from-[#4fd08a] peer-checked:to-[#1fae5b] peer-checked:text-white',
        'setengah' => 'peer-checked:bg-linear-to-b peer-checked:from-[#fcd34d] peer-checked:to-[#eba50f] peer-checked:text-white',
        'izin' => 'peer-checked:bg-sky-500 peer-checked:text-white',
        'sakit' => 'peer-checked:bg-violet-500 peer-checked:text-white',
        'alpa' => 'peer-checked:bg-linear-to-b peer-checked:from-[#fb7185] peer-checked:to-[#e11d48] peer-checked:text-white',
        'libur' => 'peer-checked:bg-slate-500 peer-checked:text-white',
    ];
    $dot = ['hadir' => 'bg-emerald-500', 'setengah' => 'bg-amber-400', 'izin' => 'bg-sky-500', 'sakit' => 'bg-violet-500', 'alpa' => 'bg-rose-500', 'libur' => 'bg-slate-400'];
@endphp

@section('actions')
    <a href="{{ route('admin.payrolls.index', ['minggu' => $date->toDateString()]) }}" class="btn">Penggajian minggu ini</a>
@endsection

@section('content')
    <form method="GET" class="card flex flex-wrap items-center gap-3 p-4 sm:p-5">
        <a href="{{ route('admin.attendances.index', ['tanggal' => $date->copy()->subDay()->toDateString()]) }}" class="tile" aria-label="Hari sebelumnya">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg>
        </a>
        <div class="text-center">
            <p class="text-[17px] font-semibold text-ink">{{ $date->translatedFormat('l, d F Y') }}</p>
            <p class="text-[12px] text-muted">{{ $date->isToday() ? 'Hari ini' : $date->diffForHumans(today(), ['parts' => 1]) }}</p>
        </div>
        <a href="{{ route('admin.attendances.index', ['tanggal' => $date->copy()->addDay()->toDateString()]) }}" class="tile" aria-label="Hari berikutnya">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
        </a>
        <input type="date" name="tanggal" value="{{ $date->toDateString() }}" class="input ml-auto w-44" onchange="this.form.submit()" aria-label="Pilih tanggal">
        @unless ($date->isToday())<a href="{{ route('admin.attendances.index') }}" class="btn">Hari ini</a>@endunless
    </form>

    <form method="POST" action="{{ route('admin.attendances.store') }}" class="card overflow-hidden" data-attendance-form>
        @csrf
        <input type="hidden" name="worked_on" value="{{ $date->toDateString() }}">
        <div class="flex flex-wrap items-center gap-3 px-5 pt-5 pb-3 sm:px-6">
            <h2 class="card-title flex-1">Absensi {{ $date->translatedFormat('d M') }}</h2>
            <button type="button" class="btn btn-sm" data-all="hadir">Semua hadir</button>
            <button type="button" class="btn btn-sm" data-all="libur">Semua libur</button>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Pekerja</th><th>Status</th><th class="w-28">Lembur (jam)</th><th>Catatan</th></tr></thead>
                <tbody>
                @forelse ($workers as $worker)
                    @php $row = $today[$worker->id] ?? null; $locked = $row?->isLocked(); @endphp
                    <tr>
                        <td class="min-w-40">
                            <p class="font-semibold text-ink">{{ $worker->name }}</p>
                            <p class="text-[11.5px] text-muted">{{ $worker->position ?: 'Pekerja' }}</p>
                        </td>
                        <td class="min-w-[420px]">
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($statuses as $key => [$label])
                                    <label class="cursor-pointer">
                                        <input type="radio" name="rows[{{ $worker->id }}][status]" value="{{ $key }}" class="peer sr-only" data-status @checked($row?->status === $key) @disabled($locked)>
                                        <span class="inline-flex h-8 items-center rounded-full bg-white px-3 text-[12px] font-medium text-ink-soft shadow-[0_3px_8px_-5px_rgb(60_66_110/0.5)] transition hover:text-ink peer-disabled:cursor-not-allowed {{ $tone[$key] }}">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($locked)<p class="mt-1 text-[11px] text-muted">Sudah masuk slip gaji — tidak bisa diubah.</p>@endif
                        </td>
                        <td><input type="number" step="0.5" min="0" max="24" name="rows[{{ $worker->id }}][overtime_hours]" value="{{ $row ? (float) $row->overtime_hours ?: '' : '' }}" class="input h-9 text-center" placeholder="0" @disabled($locked) aria-label="Lembur {{ $worker->name }}"></td>
                        <td><input name="rows[{{ $worker->id }}][notes]" value="{{ $row?->notes }}" class="input h-9 text-[13px]" placeholder="—" @disabled($locked) aria-label="Catatan {{ $worker->name }}"></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-muted">Belum ada pekerja aktif. <a href="{{ route('admin.workers.create') }}" class="link">Tambah pekerja</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($workers->isNotEmpty())
            <div class="flex justify-end border-t border-line px-5 py-4 sm:px-6"><button class="btn btn-primary">Simpan absensi</button></div>
        @endif
    </form>

    <section class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Rekap minggu {{ $days->first()->translatedFormat('d M') }} – {{ $days->last()->translatedFormat('d M Y') }}</h2>
            <div class="hidden flex-wrap gap-3 text-[11px] text-muted md:flex">
                @foreach ($statuses as $key => [$label])<span class="flex items-center gap-1"><span class="size-2 rounded-full {{ $dot[$key] }}"></span>{{ $label }}</span>@endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Pekerja</th>
                    @foreach ($days as $day)
                        <th @class(['text-center', 'text-brand-600' => $day->isSameDay($date)])>
                            <a href="{{ route('admin.attendances.index', ['tanggal' => $day->toDateString()]) }}" class="hover:text-ink">{{ $day->translatedFormat('D') }}<br><span class="text-[13px] font-semibold">{{ $day->format('d') }}</span></a>
                        </th>
                    @endforeach
                    <th class="num">Hari kerja</th><th class="num">Lembur</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($workers as $worker)
                    @php $rows = ($records[$worker->id] ?? collect())->keyBy(fn ($r) => $r->worked_on->toDateString()); @endphp
                    <tr>
                        <td class="font-medium text-ink">{{ $worker->name }}</td>
                        @foreach ($days as $day)
                            @php $cell = $rows[$day->toDateString()] ?? null; @endphp
                            <td class="text-center">
                                @if ($cell)
                                    <span class="inline-flex size-7 items-center justify-center rounded-full text-[10px] font-bold text-white {{ $dot[$cell->status] }}" title="{{ $cell->label() }}{{ (float) $cell->overtime_hours ? ' · lembur '.qty($cell->overtime_hours, 1).' jam' : '' }}">{{ mb_substr($cell->label(), 0, 1) }}</span>
                                @else
                                    <span class="text-muted">·</span>
                                @endif
                            </td>
                        @endforeach
                        <td class="num font-semibold">{{ qty($rows->sum(fn ($r) => \App\Models\Attendance::STATUSES[$r->status][1]), 1) }}</td>
                        <td class="num">{{ qty($rows->sum('overtime_hours'), 1) }} jam</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-all]').forEach(button => button.addEventListener('click', () => {
        document.querySelectorAll(`[data-status][value="${button.dataset.all}"]:not(:disabled)`).forEach(radio => { radio.checked = true; });
    }));
</script>
@endpush
