@extends('layouts.admin', ['title' => 'Jam Live', 'subtitle' => 'Tiap sesi live: tanggal, host, jam mulai–selesai (atau jam manual). Total jam masuk ke Gaji Host.'])

@section('actions')
    <a href="{{ route('admin.live-sessions.create') }}" class="btn btn-primary">+ Sesi live</a>
@endsection

@section('content')
    <x-admin.filters>
        <x-admin.select name="host" label="Host" :options="$hosts->pluck('name', 'id')" :value="request('host')" placeholder="Semua" />
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Host</th><th>Mulai</th><th>Selesai</th><th class="num">Jam manual</th><th class="num">Total jam</th><th>Akun / toko</th><th>Catatan</th><th></th></tr></thead>
                <tbody>
                @forelse ($sessions as $session)
                    <tr>
                        <td class="whitespace-nowrap">{{ $session->date->translatedFormat('d M Y') }}</td>
                        <td>{{ $session->host?->name }}</td>
                        <td>{{ $session->start_time ? substr($session->start_time, 0, 5) : '—' }}</td>
                        <td>{{ $session->end_time ? substr($session->end_time, 0, 5) : '—' }}</td>
                        <td class="num">{{ $session->manual_hours !== null ? qty($session->manual_hours, 2) : '' }}</td>
                        <td class="num font-semibold">{{ qty($session->totalHours(), 2) }}</td>
                        <td>{{ $session->store_account }}</td>
                        <td class="text-xs text-slate-500">{{ $session->notes }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.live-sessions.edit', $session) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.live-sessions.destroy', $session)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-8 text-center text-slate-500">Belum ada sesi live tercatat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $sessions->links() }}
@endsection
