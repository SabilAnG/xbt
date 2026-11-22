@php $isHost = $kind === 'host'; @endphp
@extends('layouts.admin', [
    'title' => $title,
    'subtitle' => $isHost
        ? 'Total jam dari Jam Live × tarif host. Setelah transfer: status "Sudah".'
        : 'Hari masuk dari Absen Tukang × upah/hari + tambahan − potongan. Setelah dibayar: status "Sudah".',
])

@section('actions')
    <a href="{{ route('admin.payrolls.create', $kind) }}" class="btn btn-primary">+ Rekap periode</a>
@endsection

@section('content')
    <div class="flex items-center justify-between rounded-xl px-5 py-3 text-sm {{ $unpaid > 0 ? 'bg-rose-600 text-white' : 'bg-slate-900 text-slate-300' }}">
        <span>Total BELUM {{ $isHost ? 'ditransfer' : 'dibayar' }}</span>
        <span class="text-base font-semibold tabular-nums">{{ rupiah($unpaid) }}</span>
    </div>

    <x-admin.filters>
        <x-admin.select name="person" :label="$isHost ? 'Host' : 'Tukang'" :options="$people->pluck('name', 'id')" :value="request('person')" placeholder="Semua" />
        <x-admin.select name="status" label="Status" :options="['Belum' => 'Belum', 'Sudah' => 'Sudah']" :value="request('status')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Periode</th><th>{{ $isHost ? 'Host' : 'Tukang' }}</th>
                    @if ($isHost)
                        <th class="num">Total jam</th><th class="num">Tarif / jam</th>
                    @else
                        <th class="num">Hari masuk</th><th class="num">Upah / hari</th><th class="num">Tambahan</th><th class="num">Potongan</th>
                    @endif
                    <th class="num">{{ $isHost ? 'Harus ditransfer' : 'Harus dibayar' }}</th><th>Rekening / dana</th><th>Status</th><th>Tgl {{ $isHost ? 'transfer' : 'bayar' }}</th><th>Catatan</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $person = $isHost ? $row->host : $row->worker; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $row->period_start->translatedFormat('d M') }} – {{ $row->period_end->translatedFormat('d M Y') }}</td>
                        <td class="font-medium">{{ $person?->name }}</td>
                        @if ($isHost)
                            <td class="num">{{ qty($row->totalHours(), 2) }}</td>
                            <td class="num">{{ rupiah($row->rate()) }}</td>
                        @else
                            <td class="num">{{ qty($row->daysPresent(), 1) }}</td>
                            <td class="num">{{ rupiah($row->dailyWage()) }}</td>
                            <td class="num">{{ (float) $row->bonus ? rupiah($row->bonus) : '' }}</td>
                            <td class="num">{{ (float) $row->deduction ? rupiah($row->deduction) : '' }}</td>
                        @endif
                        <td class="num font-semibold">{{ rupiah($row->amountDue()) }}</td>
                        <td class="text-xs">{{ $person?->account }}</td>
                        <td><span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', 'bg-emerald-50 text-emerald-700 ring-emerald-200' => $row->status === 'Sudah', 'bg-rose-50 text-rose-700 ring-rose-200' => $row->status !== 'Sudah'])>{{ $row->status }}</span></td>
                        <td class="text-xs whitespace-nowrap">{{ $row->paid_at?->translatedFormat('d M Y') }}</td>
                        <td class="text-xs text-slate-500">{{ $row->notes }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.payrolls.edit', [$kind, $row->id]) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.payrolls.destroy', [$kind, $row->id])" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="py-8 text-center text-slate-500">Belum ada rekap.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $rows->links() }}
@endsection
