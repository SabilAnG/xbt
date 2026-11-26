@extends('layouts.admin', ['title' => 'Penggajian'])

@section('actions')
    <a href="{{ route('admin.attendances.index', ['tanggal' => $start->toDateString()]) }}" class="btn">Absensi</a>
    <form method="POST" action="{{ route('admin.payrolls.store-all') }}">
        @csrf
        <input type="hidden" name="period_start" value="{{ $start->toDateString() }}">
        <input type="hidden" name="period_end" value="{{ $end->toDateString() }}">
        <button class="btn btn-primary">Buat semua slip minggu ini</button>
    </form>
@endsection

@section('content')
    <div class="card flex flex-wrap items-center gap-3 p-4 sm:p-5">
        <a href="{{ route('admin.payrolls.index', ['minggu' => $start->copy()->subWeek()->toDateString()]) }}" class="tile" aria-label="Minggu sebelumnya">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 6-6 6 6 6"/></svg>
        </a>
        <div class="text-center">
            <p class="text-[17px] font-semibold text-ink">{{ $start->translatedFormat('d M') }} – {{ $end->translatedFormat('d M Y') }}</p>
            <p class="text-[12px] text-muted">Periode gaji mingguan</p>
        </div>
        <a href="{{ route('admin.payrolls.index', ['minggu' => $start->copy()->addWeek()->toDateString()]) }}" class="tile" aria-label="Minggu berikutnya">
            <svg fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 6 6 6-6 6"/></svg>
        </a>
        <span class="ml-auto rounded-full bg-rose-50 px-3 py-1 text-[12px] font-semibold text-rose-600">Belum dibayar {{ rupiah($unpaid) }}</span>
    </div>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Rekap belum digaji</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Pekerja</th><th class="num">Hari</th><th class="num">½ hari</th><th class="num">Lembur</th><th class="num">Upah</th><th class="num">Makan</th><th class="num">Lembur</th><th class="num">Borongan</th><th class="num">Kasbon</th><th class="num">Diterima</th><th></th></tr></thead>
                <tbody>
                @forelse ($summaries as $row)
                    <tr @class(['[&_td]:text-muted' => $row['attendances']->isEmpty() && $row['pieces']->isEmpty()])>
                        <td class="font-medium text-ink"><a href="{{ route('admin.workers.show', $row['worker']) }}" class="link">{{ $row['worker']->name }}</a></td>
                        <td class="num">{{ qty($row['days_full']) }}</td>
                        <td class="num">{{ qty($row['days_half']) }}</td>
                        <td class="num">{{ qty($row['overtime_hours'], 1) }} jam</td>
                        <td class="num">{{ rupiah($row['wage_total']) }}</td>
                        <td class="num">{{ rupiah($row['meal_total']) }}</td>
                        <td class="num">{{ rupiah($row['overtime_total']) }}</td>
                        <td class="num">{{ $row['piece_total'] ? rupiah($row['piece_total']) : '—' }}</td>
                        <td class="num text-rose-600">{{ $row['advance_deduction'] ? '−'.rupiah($row['advance_deduction']) : '—' }}</td>
                        <td class="num font-semibold text-ink">{{ rupiah($row['net_pay']) }}</td>
                        <td class="text-right">
                            @if ($row['attendances']->isNotEmpty() || $row['pieces']->isNotEmpty())
                                <form method="POST" action="{{ route('admin.payrolls.store') }}">
                                    @csrf
                                    <input type="hidden" name="worker_id" value="{{ $row['worker']->id }}">
                                    <input type="hidden" name="period_start" value="{{ $start->toDateString() }}">
                                    <input type="hidden" name="period_end" value="{{ $end->toDateString() }}">
                                    <button class="btn btn-sm">Buat slip</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="py-10 text-center text-muted">Belum ada pekerja aktif.</td></tr>
                @endforelse
                </tbody>
                @if ($summaries->isNotEmpty())
                    <tfoot class="font-semibold">
                    <tr>
                        <td colspan="4">Total</td>
                        <td class="num">{{ rupiah($summaries->sum('wage_total')) }}</td>
                        <td class="num">{{ rupiah($summaries->sum('meal_total')) }}</td>
                        <td class="num">{{ rupiah($summaries->sum('overtime_total')) }}</td>
                        <td class="num">{{ rupiah($summaries->sum('piece_total')) }}</td>
                        <td class="num text-rose-600">−{{ rupiah($summaries->sum('advance_deduction')) }}</td>
                        <td class="num">{{ rupiah($summaries->sum('net_pay')) }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Slip gaji</h2><span class="text-[12px] text-muted">minggu ini & yang belum dibayar</span></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Nomor</th><th>Pekerja</th><th>Periode</th><th class="num">Kotor</th><th class="num">Kasbon</th><th class="num">Dibayar</th><th>Status</th></tr></thead>
                <tbody>
                @forelse ($slips as $slip)
                    <tr>
                        <td class="font-mono text-xs"><a href="{{ route('admin.payrolls.show', $slip) }}" class="link">{{ $slip->payroll_number }}</a></td>
                        <td>{{ $slip->worker->name }}</td>
                        <td class="whitespace-nowrap">{{ $slip->periodLabel() }}</td>
                        <td class="num">{{ rupiah($slip->gross_pay) }}</td>
                        <td class="num text-rose-600">{{ (float) $slip->advance_deduction ? '−'.rupiah($slip->advance_deduction) : '—' }}</td>
                        <td class="num font-semibold">{{ rupiah($slip->net_pay) }}</td>
                        <td><x-admin.status :value="$slip->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-muted">Belum ada slip gaji.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
