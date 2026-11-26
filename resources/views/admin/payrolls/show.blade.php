@extends('layouts.admin', ['title' => 'Slip gaji '.$payroll->payroll_number, 'subtitle' => $payroll->worker->name.' · '.$payroll->periodLabel()])

@section('actions')
    <button type="button" class="btn" onclick="window.print()">Cetak</button>
    @if ($payroll->isPaid())
        <form method="POST" action="{{ route('admin.payrolls.unpay', $payroll) }}" data-confirm="Batalkan pembayaran gaji ini? Pengeluaran dan mutasi dompetnya dihapus.">
            @csrf<button class="btn btn-danger">Batalkan pembayaran</button>
        </form>
    @endif
@endsection

@section('content')
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="card p-5 sm:p-7">
            <div class="flex flex-wrap items-start gap-4">
                <span class="grid size-14 place-items-center rounded-[16px] bg-linear-to-br from-[#ffb36b] to-[#d9573a] text-[17px] font-bold text-white shadow-(--shadow-soft)">
                    {{ strtoupper(collect(explode(' ', $payroll->worker->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[18px] font-semibold text-ink">{{ $payroll->worker->name }}</p>
                    <p class="text-[12.5px] text-muted">{{ $payroll->worker->position ?: 'Pekerja' }} · {{ $payroll->periodLabel() }}</p>
                </div>
                <x-admin.status :value="$payroll->statusLabel()" />
            </div>

            <dl class="mt-6 space-y-2.5 text-[13.5px]">
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">Upah · {{ qty($payroll->days_full) }} hari{{ (float) $payroll->days_half ? ' + '.qty($payroll->days_half).' × ½ hari' : '' }} × {{ rupiah($payroll->daily_wage) }}</dt><dd class="font-medium text-ink tabular-nums">{{ rupiah($payroll->wage_total) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">Uang makan · {{ qty($payroll->days_full + $payroll->days_half) }} hari × {{ rupiah($payroll->meal_allowance) }}</dt><dd class="font-medium text-ink tabular-nums">{{ rupiah($payroll->meal_total) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">Lembur · {{ qty($payroll->overtime_hours, 1) }} jam × {{ rupiah($payroll->overtime_rate) }}</dt><dd class="font-medium text-ink tabular-nums">{{ rupiah($payroll->overtime_total) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-line pt-2.5"><dt class="font-semibold text-ink">Gaji kotor</dt><dd class="font-semibold text-ink tabular-nums">{{ rupiah($payroll->gross_pay) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-soft">Potong kasbon</dt><dd class="font-medium text-rose-600 tabular-nums">−{{ rupiah($payroll->advance_deduction) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-line pt-3 text-[18px]"><dt class="font-semibold text-ink">Diterima</dt><dd class="font-bold text-brand-600 tabular-nums">{{ rupiah($payroll->net_pay) }}</dd></div>
            </dl>

            <div class="mt-6 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Tanggal</th><th>Status</th><th class="num">Lembur</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @foreach ($payroll->attendances as $row)
                        <tr>
                            <td>{{ $row->worked_on->translatedFormat('D, d M') }}</td>
                            <td>{{ $row->label() }}</td>
                            <td class="num">{{ (float) $row->overtime_hours ? qty($row->overtime_hours, 1).' jam' : '—' }}</td>
                            <td class="text-[12px] text-muted">{{ $row->notes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="space-y-5">
            @if ($payroll->isPaid())
                <section class="card p-5 sm:p-6">
                    <h2 class="card-title">Sudah dibayar</h2>
                    <p class="mt-2 text-[13px] text-ink-soft">{{ $payroll->paid_at->translatedFormat('d M Y') }} dari <strong>{{ $payroll->wallet?->name ?? '—' }}</strong>. Tercatat di Pengeluaran kategori Gaji pekerja.</p>
                </section>
            @else
                <form method="POST" action="{{ route('admin.payrolls.pay', $payroll) }}" class="card space-y-4 p-5 sm:p-6">
                    @csrf
                    <h2 class="card-title">Bayar gaji</h2>
                    <x-admin.field name="paid_at" label="Tanggal bayar" type="date" :value="now()->toDateString()" required />
                    <x-admin.wallet-select name="wallet_id" label="Dari dompet" placeholder="— pilih dompet —" />
                    <button class="btn btn-primary w-full">Bayar {{ rupiah($payroll->net_pay) }}</button>
                </form>
                <x-admin.delete-button :action="route('admin.payrolls.destroy', $payroll)" label="Hapus slip" confirm="Hapus slip gaji ini? Absensinya bisa digaji ulang." />
            @endif
        </aside>
    </div>
@endsection
