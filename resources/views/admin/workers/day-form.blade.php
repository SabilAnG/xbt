@extends('layouts.admin', ['title' => $day->exists ? 'Ubah absen' : 'Absen tukang', 'subtitle' => '1 = masuk, 0,5 = setengah hari, kosong = libur'])

@section('content')
    <form method="POST" action="{{ $day->exists ? route('admin.attendance.update', $day) : route('admin.attendance.store') }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        @if ($day->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="date" label="Tanggal" type="date" :value="$day->date?->toDateString()" required />
            <x-admin.field name="notes" label="Catatan" :value="$day->notes" class="sm:col-span-2" placeholder="mis. libur idul adha" />
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($workers as $worker)
                @php $value = old('values.'.$worker->id, $day->exists ? $day->valueFor($worker) : 1); @endphp
                <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-4 py-3">
                    <span class="font-medium">{{ $worker->name }}</span>
                    <select name="values[{{ $worker->id }}]" class="input w-40">
                        <option value="1" @selected((string) $value === '1' || (float) $value === 1.0)>1 — masuk</option>
                        <option value="0.5" @selected((float) $value === 0.5)>0,5 — setengah</option>
                        <option value="" @selected($value === null || $value === '')>— libur</option>
                    </select>
                </div>
            @endforeach
        </div>
        @error('values.*')<p class="error">{{ $message }}</p>@enderror
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.attendance.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan absen</button>
        </div>
    </form>
@endsection
