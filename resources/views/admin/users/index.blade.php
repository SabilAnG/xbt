@extends('layouts.admin', ['title' => 'Pengguna & Akses'])

@php
    $featureCount = collect($features)->flatten()->count();
    $roleOptions = $roles->pluck('name', 'key');
    $openRole = old('_role_form');
@endphp

@section('actions')
    <a href="{{ route('admin.users.activity') }}" class="btn">Riwayat aktivitas</a>
@endsection

@section('content')
    {{-- ------------------------------------------------ Peran & hak akses --}}
    <section class="space-y-3">
        <div class="flex items-end justify-between gap-3">
            <div>
                <h2 class="card-title">Peran &amp; hak akses</h2>
                <p class="text-[12.5px] text-muted">Klik peran untuk mengatur menu apa saja yang boleh dibuka.</p>
            </div>
            <button type="button" class="btn btn-primary btn-sm" data-role-open="new">+ Tambah peran</button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($roles as $role)
                <button type="button" @class(["card group p-4 text-left transition", "hover:-translate-y-0.5 hover:shadow-(--shadow-pop)" => ! $role->is_system, "cursor-default" => $role->is_system]) @unless ($role->is_system) data-role-open="{{ $role->id }}" @endunless @if ($role->is_system) title="Pemilik selalu bisa membuka semua menu" @endif>
                    <span class="flex items-start justify-between gap-2">
                        <span class="text-[13.5px] font-semibold text-ink">{{ $role->name }}</span>
                        @if ($role->is_system)
                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10.5px] font-semibold text-brand-700">Terkunci</span>
                        @else
                            <svg class="size-4 text-muted transition group-hover:text-accent" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        @endif
                    </span>
                    <span class="mt-1 block text-[12px] text-muted">{{ $role->description ?: '—' }}</span>
                    <span class="mt-3 flex items-center justify-between text-[11.5px]">
                        <span class="text-ink-soft">{{ $role->users_count }} pengguna</span>
                        <span class="rounded-full bg-chip px-2 py-0.5 font-medium text-ink-soft">
                            {{ $role->is_system ? 'Semua + pengguna' : ($role->all_access ? 'Semua menu' : count($role->permissions ?? []).' / '.$featureCount.' menu') }}
                        </span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Editor peran (satu terbuka) --}}
        @foreach ($roles->reject->is_system->push(null) as $role)
            @php
                $key = $role?->id ?? 'new';
                $checked = old('_role_form') == $key ? old('permissions', []) : ($role?->permissions ?? []);
                $all = old('_role_form') == $key ? (bool) old('all_access') : (bool) $role?->all_access;
            @endphp
            <form method="POST" action="{{ $role ? route('admin.users.roles.update', $role) : route('admin.users.roles.store') }}"
                  class="card space-y-4 p-5" data-role-editor="{{ $key }}" @if ((string) $openRole !== (string) $key) hidden @endif>
                @csrf
                @if ($role) @method('PUT') @endif
                <input type="hidden" name="_role_form" value="{{ $key }}">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="card-title">{{ $role ? 'Atur akses: '.$role->name : 'Peran baru' }}</h3>
                    <button type="button" class="btn btn-sm" data-role-close>Tutup</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-[minmax(0,260px)_minmax(0,1fr)]">
                    <x-admin.field name="name" label="Nama peran" :value="old('_role_form') == $key ? old('name') : $role?->name" required placeholder="mis. Admin Gudang" />
                    <x-admin.field name="description" label="Keterangan" :value="old('_role_form') == $key ? old('description') : $role?->description" placeholder="mis. Hanya input pembelian & penerimaan" />
                </div>

                <label class="flex items-center gap-2.5 rounded-[12px] border border-line px-4 py-3 text-[13px]">
                    <input type="checkbox" name="all_access" value="1" class="size-4" @checked($all) data-all-access>
                    <span><strong class="text-ink">Semua menu</strong> <span class="text-muted">— termasuk menu baru nanti (kecuali kelola pengguna)</span></span>
                </label>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-feature-grid @if ($all) hidden @endif>
                    @foreach ($features as $group => $items)
                        <fieldset class="rounded-[12px] border border-line p-3.5" data-feature-group>
                            <label class="flex items-center gap-2 border-b border-line pb-2 text-[13px] font-semibold text-ink">
                                <input type="checkbox" class="size-4" data-group-toggle> {{ $group }}
                            </label>
                            <div class="mt-2 space-y-1.5">
                                @foreach ($items as $feature => $label)
                                    <label class="flex items-center gap-2 text-[12.5px] text-ink-soft">
                                        <input type="checkbox" name="permissions[]" value="{{ $feature }}" class="size-4" @checked(in_array($feature, $checked, true))> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @if (old('_role_form') == $key)
                    @error('permissions')<p class="error">{{ $message }}</p>@enderror
                @endif

                <div class="flex items-center justify-between gap-2 border-t border-line pt-4">
                    <div>
                        @if ($role)
                            <button form="delete-role-{{ $role->id }}" class="text-[12.5px] text-rose-600 hover:underline">Hapus peran</button>
                        @endif
                    </div>
                    <button class="btn btn-primary">{{ $role ? 'Simpan akses' : 'Tambah peran' }}</button>
                </div>
            </form>
            @if ($role)
                <form id="delete-role-{{ $role->id }}" method="POST" action="{{ route('admin.users.roles.destroy', $role) }}" class="hidden"
                      data-confirm="Hapus peran {{ $role->name }}?" data-confirm-tone="danger">@csrf @method('DELETE')</form>
            @endif
        @endforeach
    </section>

    {{-- ------------------------------------------------ Pengguna --}}
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-3">
            <h2 class="card-title">Pengguna</h2>
            @foreach ($users as $account)
                <form method="POST" action="{{ route('admin.users.update', $account) }}" class="card grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_190px_150px_auto]">
                    @csrf
                    @method('PUT')
                    <x-admin.field name="name" label="Nama" :value="$account->name" required />
                    <x-admin.field name="email" label="Email" type="email" :value="$account->email" required />
                    <x-admin.select name="role" label="Peran" :options="$roleOptions" :value="$account->role" :placeholder="false" required />
                    <x-admin.field name="password" label="Password baru" type="password" placeholder="kosongkan" />
                    <div class="flex items-end gap-2">
                        <label class="flex h-10 items-center gap-1.5 text-[12.5px]"><input type="checkbox" name="is_active" value="1" @checked($account->is_active)> Aktif</label>
                        <button class="btn btn-primary btn-sm">Simpan</button>
                    </div>
                    @if (auth()->user()->isNot($account))
                        <div class="-mt-1 flex justify-end sm:col-span-2 xl:col-span-5">
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
            <x-admin.select name="role" label="Peran" :options="$roleOptions" :value="$roles->firstWhere('key', 'gudang')?->key ?? $roles->last()?->key" :placeholder="false" required />
            <x-admin.field name="password" label="Password" type="password" required hint="Minimal 8 karakter." />
            <button class="btn btn-primary w-full">Tambah</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const editors = [...document.querySelectorAll('[data-role-editor]')];
        const show = key => editors.forEach(form => {
            form.hidden = form.dataset.roleEditor !== String(key);
            if (!form.hidden) form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

        document.querySelectorAll('[data-role-open]').forEach(button => button.addEventListener('click', () => {
            show(button.dataset.roleOpen);
        }));
        document.querySelectorAll('[data-role-close]').forEach(button => button.addEventListener('click', () => show(null)));

        editors.forEach(form => {
            const all = form.querySelector('[data-all-access]');
            const grid = form.querySelector('[data-feature-grid]');
            all.addEventListener('change', () => grid.hidden = all.checked);

            // Centang grup = semua menu di grup itu; status grup mengikuti isinya.
            form.querySelectorAll('[data-feature-group]').forEach(group => {
                const toggle = group.querySelector('[data-group-toggle]');
                const boxes = [...group.querySelectorAll('input[name="permissions[]"]')];
                const sync = () => {
                    const on = boxes.filter(box => box.checked).length;
                    toggle.checked = on === boxes.length;
                    toggle.indeterminate = on > 0 && on < boxes.length;
                };
                toggle.addEventListener('change', () => { boxes.forEach(box => box.checked = toggle.checked); });
                boxes.forEach(box => box.addEventListener('change', sync));
                sync();
            });
        });
    })();
</script>
@endpush
