@extends('layouts.admin', ['title' => 'Master Satuan', 'subtitle' => 'Satuan yang bisa dipilih di Master Item sebagai satuan beli dan satuan pakai. Konversinya (mis. 1 lembar = 28.800 cm²) diisi per item.'])

@section('content')
    <div class="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card overflow-hidden">
            <table class="table">
                <thead><tr><th>Satuan</th><th>Keterangan & urutan</th><th></th></tr></thead>
                <tbody>
                @forelse ($units as $unit)
                    <tr>
                        <td class="font-semibold">{{ $unit->name }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.units.update', $unit) }}" class="flex gap-2">
                                @csrf
                                @method('PUT')
                                <input name="description" value="{{ $unit->description }}" class="input" aria-label="Keterangan {{ $unit->name }}">
                                <input name="sort_order" type="number" min="0" value="{{ $unit->sort_order }}" class="input w-20" aria-label="Urutan {{ $unit->name }}">
                                <button class="btn btn-sm">Simpan</button>
                            </form>
                        </td>
                        <td class="text-right"><x-admin.delete-button :action="route('admin.units.destroy', $unit)" label="✕" :confirm="'Hapus satuan '.$unit->name.'?'" /></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-8 text-center text-slate-500">Belum ada satuan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        <form method="POST" action="{{ route('admin.units.store') }}" class="card space-y-4 self-start p-6">
            @csrf
            <h2 class="card-title">Tambah satuan</h2>
            <x-admin.field name="name" label="Nama satuan" required placeholder="mis. Rim" />
            <x-admin.field name="description" label="Keterangan" />
            <div class="flex justify-end"><button class="btn btn-primary">Tambah</button></div>
        </form>
    </div>
@endsection
