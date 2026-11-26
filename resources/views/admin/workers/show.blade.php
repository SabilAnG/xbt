@extends('layouts.admin', ['title' => $worker->name, 'subtitle' => ($worker->position ?: 'Pekerja').($worker->phone ? ' · '.$worker->phone : '').($worker->is_active ? '' : ' · nonaktif')])

@php $statusTone = ['hadir' => 'bg-emerald-50 text-emerald-700', 'setengah' => 'bg-amber-50 text-amber-700', 'izin' => 'bg-sky-50 text-sky-700', 'sakit' => 'bg-violet-50 text-violet-700', 'alpa' => 'bg-rose-50 text-rose-600', 'libur' => 'bg-surface text-muted']; @endphp

@section('actions')
    <a href="{{ route('admin.attendances.index') }}" class="btn">Absensi</a>
    <a href="{{ route('admin.workers.edit', $worker) }}" class="btn btn-primary">Ubah</a>
@endsection

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Upah harian" :value="rupiah($worker->daily_wage)" :hint="'Makan '.rupiah($worker->meal_allowance).' · lembur '.rupiah($worker->overtime_rate).'/jam'" />
        <x-admin.stat label="Hadir bulan ini" :value="(($month['hadir'] ?? 0) + ($month['setengah'] ?? 0)).' hari'" :hint="($month['setengah'] ?? 0).' × ½ hari · '.($month['alpa'] ?? 0).' alpa'" />
        <x-admin.stat label="Sisa kasbon" :value="rupiah($balance)" trend="down" :tone="$balance > 0 ? 'danger' : 'default'" />
        <x-admin.stat label="Slip belum dibayar" :value="rupiah($payrolls->where('status', 'draft')->sum('net_pay'))" :href="route('admin.payrolls.index')" />
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-5">
            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Absensi terakhir</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Tanggal</th><th>Status</th><th class="num">Lembur</th><th>Catatan</th><th>Slip</th></tr></thead>
                        <tbody>
                        @forelse ($attendances as $row)
                            <tr>
                                <td class="whitespace-nowrap">{{ $row->worked_on->translatedFormat('D, d M Y') }}</td>
                                <td><span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusTone[$row->status] }}">{{ $row->label() }}</span></td>
                                <td class="num">{{ (float) $row->overtime_hours ? qty($row->overtime_hours, 1).' jam' : '—' }}</td>
                                <td class="text-[12px] text-muted">{{ $row->notes }}</td>
                                <td class="text-[12px]">{{ $row->worker_payroll_id ? 'Sudah' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-muted">Belum ada absensi.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Slip gaji</h2><a href="{{ route('admin.payrolls.index') }}" class="btn btn-sm">Penggajian</a></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Nomor</th><th>Periode</th><th class="num">Kotor</th><th class="num">Kasbon</th><th class="num">Dibayar</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td class="font-mono text-xs"><a href="{{ route('admin.payrolls.show', $payroll) }}" class="link">{{ $payroll->payroll_number }}</a></td>
                                <td class="whitespace-nowrap">{{ $payroll->periodLabel() }}</td>
                                <td class="num">{{ rupiah($payroll->gross_pay) }}</td>
                                <td class="num text-rose-600">{{ (float) $payroll->advance_deduction ? '−'.rupiah($payroll->advance_deduction) : '—' }}</td>
                                <td class="num font-semibold">{{ rupiah($payroll->net_pay) }}</td>
                                <td><x-admin.status :value="$payroll->statusLabel()" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-8 text-center text-muted">Belum ada slip gaji.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <form method="POST" action="{{ route('admin.workers.advances.store', $worker) }}" class="card space-y-4 p-5 sm:p-6">
                @csrf
                <div>
                    <h2 class="card-title">Kasbon</h2>
                    <p class="text-[12px] text-muted">Uang keluar dari dompet; dipotong otomatis saat penggajian.</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.field name="given_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                    <x-admin.field name="amount" label="Nominal (Rp)" type="number" step="any" min="0" required />
                </div>
                <x-admin.wallet-select name="wallet_id" label="Dari dompet" placeholder="— pilih dompet —" />
                <x-admin.field name="notes" label="Keterangan" />
                <div class="flex justify-end"><button class="btn btn-primary">Catat kasbon</button></div>
            </form>

            <section class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Riwayat kasbon</h2><span class="text-[12px] text-muted">sisa {{ rupiah($balance) }}</span></div>
                <ul class="space-y-2 px-5 pb-5">
                    @forelse ($advances as $advance)
                        <li class="flex items-center gap-3 rounded-[12px] bg-white/80 px-3.5 py-2.5 shadow-[0_4px_12px_-10px_rgb(60_66_110/0.5)]">
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-ink">{{ rupiah($advance->amount) }}</p>
                                <p class="truncate text-[11.5px] text-muted">{{ $advance->given_at->translatedFormat('d M Y') }} · {{ $advance->wallet?->name ?? '—' }}{{ $advance->notes ? ' · '.$advance->notes : '' }}</p>
                            </div>
                            <x-admin.delete-button :action="route('admin.workers.advances.destroy', $advance)" label="✕" confirm="Hapus kasbon ini? Saldo dompet dikembalikan." />
                        </li>
                    @empty
                        <li class="py-6 text-center text-[13px] text-muted">Belum ada kasbon.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
@endsection
