@extends('layouts.admin', ['title' => $worker->exists ? 'Ubah pekerja' : 'Pekerja baru'])

@section('content')
    <form method="POST" action="{{ $worker->exists ? route('admin.workers.update', $worker) : route('admin.workers.store') }}" class="max-w-3xl space-y-5">
        @csrf
        @if ($worker->exists) @method('PUT') @endif

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Data pekerja</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="name" label="Nama" :value="$worker->name" required class="sm:col-span-2" />
                <x-admin.field name="position" label="Jabatan" :value="$worker->position" placeholder="mis. Tukang las" list="worker-positions" />
                <x-admin.field name="phone" label="Telepon / WA" :value="$worker->phone" />
                <x-admin.field name="joined_at" label="Mulai bekerja" type="date" :value="optional($worker->joined_at)->toDateString()" />
                <label class="flex items-end gap-2 pb-2.5 text-sm text-ink">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $worker->is_active))> Masih aktif bekerja
                </label>
            </div>
            <datalist id="worker-positions">@foreach (['Tukang las', 'Tukang bending', 'Tukang potong', 'Finishing / poles', 'Packing', 'Helper'] as $position)<option value="{{ $position }}">@endforeach</datalist>
        </section>

        <section class="card space-y-4 p-6">
            <h2 class="card-title">Upah</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="daily_wage" label="Upah harian (Rp)" type="number" step="any" min="0" :value="$worker->exists ? (float) $worker->daily_wage : null" required hint="½ hari dihitung setengah." />
                <x-admin.field name="meal_allowance" label="Uang makan / hari (Rp)" type="number" step="any" min="0" :value="$worker->exists ? (float) $worker->meal_allowance : null" hint="Untuk hari masuk (penuh & ½ hari)." />
                <x-admin.field name="overtime_rate" label="Tarif lembur / jam (Rp)" type="number" step="any" min="0" :value="$worker->exists ? (float) $worker->overtime_rate : null" />
            </div>
            <x-admin.field name="notes" label="Catatan" type="textarea" :value="$worker->notes" />
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ $worker->exists ? route('admin.workers.show', $worker) : route('admin.workers.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>

    @if ($worker->exists)
        <div class="max-w-3xl border-t border-line pt-4">
            <x-admin.delete-button :action="route('admin.workers.destroy', $worker)" label="Hapus pekerja" />
        </div>
    @endif
@endsection
