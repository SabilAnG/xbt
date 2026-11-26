@extends('layouts.admin', ['title' => 'Pengguna & Akses'])

@section('content')
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($roles as $key => $role)
            <div class="card p-4">
                <p class="text-[13px] font-semibold text-ink">{{ $role['label'] }}</p>
                <p class="mt-1 text-[12px] text-muted">{{ $role['hint'] }}</p>
                <p class="mt-2 text-[11.5px] text-ink-soft">{{ $users->where('role', $key)->count() }} pengguna</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-3">
            @foreach ($users as $account)
                <form method="POST" action="{{ route('admin.users.update', $account) }}" class="card grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_170px_150px_auto]">
                    @csrf
                    @method('PUT')
                    <x-admin.field name="name" label="Nama" :value="$account->name" required />
                    <x-admin.field name="email" label="Email" type="email" :value="$account->email" required />
                    <x-admin.select name="role" label="Peran" :options="collect($roles)->map(fn ($role) => $role['label'])" :value="$account->role" :placeholder="false" required />
                    <x-admin.field name="password" label="Password baru" type="password" placeholder="kosongkan" />
                    <div class="flex items-end gap-2">
                        <label class="flex h-10 items-center gap-1.5 text-[12.5px]"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Aktif</label>
                        <button class="btn btn-primary btn-sm">Simpan</button>
                    </div>
                    @if (auth()->user()->isNot($account))
                        <div class="sm:col-span-2 xl:col-span-5 -mt-1 flex justify-end">
                            <button form="delete-user-{{ $account->id }}" class="text-[12px] text-rose-600 hover:underline">Hapus pengguna</button>
                        </div>
                    @endif
                </form>
                <form id="delete-user-{{ $account->id }}" method="POST" action="{{ route('admin.users.destroy', $account) }}" data-confirm="Hapus akun {{ $account->name }}? Dia tidak bisa masuk lagi." data-confirm-tone="danger" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </section>

        <form method="POST" action="{{ route('admin.users.store') }}" class="card space-y-4 self-start p-5">
            @csrf
            <h2 class="card-title">Tambah pengguna</h2>
            <x-admin.field name="name" label="Nama" required />
            <x-admin.field name="email" label="Email (untuk login)" type="email" required />
            <x-admin.select name="role" label="Peran" :options="collect($roles)->map(fn ($role) => $role['label'])" value="gudang" :placeholder="false" required />
            <x-admin.field name="password" label="Password" type="password" required hint="Minimal 8 karakter." />
            <button class="btn btn-primary w-full">Tambah</button>
        </form>
    </div>
@endsection
