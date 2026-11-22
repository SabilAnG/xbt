@extends('layouts.admin', ['title' => ($row->exists ? 'Ubah ' : 'Tambah ').$config['singular'], 'subtitle' => $config['subtitle']])

@section('content')
    <form method="POST" action="{{ $row->exists ? route('admin.masters.update', [$master, $row->id]) : route('admin.masters.store', $master) }}" class="card max-w-4xl space-y-6 p-6">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($config['fields'] as $name => $field)
                @php
                    $span = match ($field['span'] ?? 1) { 2 => 'sm:col-span-2', 3 => 'sm:col-span-3', default => '' };
                    $value = $row->{$name};
                @endphp
                @if ($field['type'] === 'checkbox')
                    <div class="flex items-end pb-2 {{ $span }}">
                        <label class="flex items-center gap-2 text-sm text-zinc-700">
                            <input type="hidden" name="{{ $name }}" value="0">
                            <input type="checkbox" name="{{ $name }}" value="1" class="rounded border-zinc-300" @checked(old($name, $value))> {{ $field['label'] }}
                        </label>
                    </div>
                @elseif ($field['type'] === 'select')
                    <x-admin.select :name="$name" :label="$field['label']" :options="value($field['options'])" :value="$value"
                        :required="$field['required'] ?? false" :hint="$field['hint'] ?? null" :class="$span" />
                @else
                    <x-admin.field :name="$name" :label="$field['label']" :type="$field['type'] === 'number' ? 'number' : 'text'"
                        :value="$field['type'] === 'number' && $value !== null ? (float) $value : $value"
                        :step="$field['type'] === 'number' ? 'any' : null" :required="$field['required'] ?? false"
                        :hint="$field['hint'] ?? null" :class="$span" />
                @endif
            @endforeach
        </div>

        @if ($master === 'mesin' && $row->exists)
            @php $b = $row->hourlyBreakdown(); @endphp
            <div class="rounded-lg bg-zinc-50 p-4 text-sm">
                Penyusutan {{ rupiah($b['penyusutan']) }} + listrik {{ rupiah($b['listrik']) }} + perawatan {{ rupiah($b['maintenance']) }}
                = <strong>{{ rupiah($b['total']) }}/jam</strong>
                <span class="text-zinc-500">(tarif listrik {{ rupiah(\App\Models\Machine::tarifListrik()) }}/kWh)</span>
            </div>
        @endif

        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
            <a href="{{ route('admin.masters.index', $master) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
