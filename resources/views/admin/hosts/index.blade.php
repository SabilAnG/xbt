@extends('layouts.admin', ['title' => 'Data Host & Tukang', 'subtitle' => 'Tarif host per jam, upah tukang per hari, dan rekening — dipakai otomatis di Gaji Host & Gaji Tukang'])

@section('content')
    @foreach ([
        ['title' => 'Host live', 'rows' => $hosts, 'rate' => 'hourly_rate', 'rateLabel' => 'Tarif / jam', 'update' => 'admin.hosts.update', 'destroy' => 'admin.hosts.destroy', 'store' => 'admin.hosts.store'],
        ['title' => 'Tukang', 'rows' => $workers, 'rate' => 'daily_wage', 'rateLabel' => 'Upah / hari', 'update' => 'admin.workers.update', 'destroy' => 'admin.workers.destroy', 'store' => 'admin.workers.store'],
    ] as $section)
        <section class="card max-w-4xl overflow-hidden">
            <div class="card-header"><h2 class="card-title">{{ $section['title'] }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nama</th><th>{{ $section['rateLabel'] }}</th><th>Rekening / Dana</th><th>Aktif</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            <td colspan="5" class="p-0">
                                <form method="POST" action="{{ route($section['update'], $row) }}" class="flex flex-wrap items-center gap-2 px-4 py-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="name" value="{{ $row->name }}" class="input w-36" required>
                                    <input name="{{ $section['rate'] }}" type="number" step="any" min="0" value="{{ (float) $row->{$section['rate']} }}" class="input w-36" required>
                                    <input name="account" value="{{ $row->account }}" class="input w-56 flex-1" placeholder="No. rekening / dana">
                                    <input type="hidden" name="is_active" value="0">
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($row->is_active)> Aktif</label>
                                    <button class="btn btn-sm">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ route($section['store']) }}" class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
                @csrf
                <input name="name" class="input w-36" placeholder="Nama baru" required>
                <input name="{{ $section['rate'] }}" type="number" step="any" min="0" class="input w-36" placeholder="{{ $section['rateLabel'] }}" required>
                <input name="account" class="input w-56 flex-1" placeholder="Rekening / dana">
                <button class="btn btn-primary btn-sm">+ Tambah</button>
            </form>
        </section>
    @endforeach
    @if ($errors->any())<p class="error">{{ $errors->first() }}</p>@endif
@endsection
