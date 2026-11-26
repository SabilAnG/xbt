@extends('layouts.admin', ['title' => 'Riwayat Aktivitas'])

@section('content')
    <x-admin.filters>
        <x-admin.field name="q" label="Cari" :value="request('q')" placeholder="No. nota, nama" />
        <x-admin.select name="pengguna" label="Pengguna" :options="$users" :value="request('pengguna')" placeholder="Semua" />
        <x-admin.select name="aksi" label="Aksi" :options="$actions" :value="request('aksi')" placeholder="Semua" />
        <x-admin.field name="dari" label="Dari" type="date" :value="request('dari')" />
        <x-admin.field name="sampai" label="Sampai" type="date" :value="request('sampai')" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Data</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-[12.5px]">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'Pengguna terhapus' }}<p class="text-[11px] text-muted">{{ $log->user?->roleLabel() }}</p></td>
                        <td>
                            <span @class(['inline-flex rounded-full px-2 py-0.5 text-[11.5px] font-semibold',
                                'bg-emerald-50 text-emerald-700' => $log->action === 'created',
                                'bg-amber-50 text-amber-700' => $log->action === 'updated',
                                'bg-rose-50 text-rose-600' => $log->action === 'deleted'])>{{ $log->actionLabel() }}</span>
                        </td>
                        <td class="min-w-72">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-10 text-center text-muted">Belum ada aktivitas tercatat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $logs->links() }}
@endsection
