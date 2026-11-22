@extends('layouts.admin', ['title' => $config['title'], 'subtitle' => $config['subtitle']])

@section('actions')
    <a href="{{ route('admin.masters.create', $master) }}" class="btn btn-primary">+ {{ ucfirst($config['singular']) }}</a>
@endsection

@section('content')
    @if ($master === 'overhead')
        @php $total = \App\Models\OverheadItem::monthlyTotal(); $target = \App\Models\OverheadItem::targetPerMonth(); @endphp
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <x-admin.stat label="Total overhead per bulan" :value="rupiah($total)" />
            <x-admin.stat label="Target produksi per bulan" :value="qty($target).' unit'" :href="route('admin.production-settings.edit')" hint="Ubah di Pengaturan Produksi" />
            <x-admin.stat label="Beban overhead per unit" :value="rupiah(\App\Models\OverheadItem::perUnit())" tone="success" />
        </section>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    @foreach ($config['columns'] as $label => $formatter)<th>{{ $label }}</th>@endforeach
                    <th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr @class(['opacity-50' => ! ($row->is_active ?? true)])>
                        @foreach ($config['columns'] as $label => $formatter)
                            <td @class(['font-medium' => $loop->first])>{{ $formatter($row) }}</td>
                        @endforeach
                        <td><x-admin.status :value="($row->is_active ?? true) ? 'AKTIF' : 'NONAKTIF'" /></td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('admin.masters.edit', [$master, $row->id]) }}" class="btn btn-sm">Ubah</a>
                            <x-admin.delete-button :action="route('admin.masters.destroy', [$master, $row->id])" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($config['columns']) + 2 }}" class="py-8 text-center text-zinc-500">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
