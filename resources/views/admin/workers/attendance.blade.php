@extends('layouts.admin', ['title' => 'Absen Tukang', 'subtitle' => 'Satu baris per hari: 1 = masuk, 0,5 = setengah hari, kosong = libur'])

@section('actions')
    <a href="{{ route('admin.attendance.create') }}" class="btn btn-primary">+ Absen hari ini</a>
@endsection

@section('content')
    <x-admin.filters>
        <div>
            <label class="label" for="month">Bulan</label>
            <input id="month" name="month" type="month" value="{{ request('month') }}" class="input">
        </div>
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Tanggal</th>
                    @foreach ($workers as $worker)<th class="num">{{ $worker->name }}</th>@endforeach
                    <th>Catatan</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($days as $day)
                    <tr>
                        <td class="whitespace-nowrap">{{ $day->date->translatedFormat('D, d M Y') }}</td>
                        @foreach ($workers as $worker)
                            @php $value = $day->valueFor($worker); @endphp
                            <td class="num {{ $value === null ? 'text-zinc-300' : 'font-semibold' }}">{{ $value === null ? '—' : qty($value, 1) }}</td>
                        @endforeach
                        <td class="text-xs text-zinc-500">{{ $day->notes }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.attendance.edit', $day) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.attendance.destroy', $day)" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $workers->count() + 3 }}" class="py-8 text-center text-zinc-500">Belum ada absen.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $days->links() }}
@endsection
