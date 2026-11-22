@extends('layouts.admin', ['title' => $session->exists ? 'Ubah sesi live' : 'Sesi live baru', 'subtitle' => 'Isi jam mulai & selesai (format 19:00), atau langsung isi jam manual'])

@section('content')
    <form method="POST" action="{{ $session->exists ? route('admin.live-sessions.update', $session) : route('admin.live-sessions.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @if ($session->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="date" label="Tanggal" type="date" :value="$session->date?->toDateString()" required />
            <x-admin.select name="host_id" label="Host" :options="$hosts->pluck('name', 'id')" :value="$session->host_id" required class="sm:col-span-2" />
            <x-admin.field name="start_time" label="Jam mulai" type="time" :value="$session->start_time ? substr($session->start_time, 0, 5) : null" />
            <x-admin.field name="end_time" label="Jam selesai" type="time" :value="$session->end_time ? substr($session->end_time, 0, 5) : null" hint="Lewat tengah malam tetap dihitung benar." />
            <x-admin.field name="manual_hours" label="Jam manual (opsional)" type="number" step="0.01" min="0" :value="$session->manual_hours" hint="Bila diisi, jam mulai/selesai diabaikan." />
            <x-admin.field name="store_account" label="Akun / toko" :value="$session->store_account" />
            <x-admin.field name="notes" label="Catatan" :value="$session->notes" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
            <a href="{{ route('admin.live-sessions.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
