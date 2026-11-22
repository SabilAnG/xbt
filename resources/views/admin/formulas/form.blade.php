@extends('layouts.admin', ['title' => $formula->exists ? 'Resep: '.$formula->name : 'Resep baru', 'subtitle' => 'Qty per knalpot. Kolom merek kosong = berlaku untuk semua merek.'])

@php
    $lines = collect(old('lines', $formula->exists
        ? $formula->materials->map(fn ($line) => ['material_id' => $line->material_id, 'brand' => $line->brand, 'qty' => (float) $line->qty])->all()
        : []));
    // Sisakan beberapa baris kosong untuk bahan baru.
    $lines = $lines->concat(array_fill(0, 5, ['material_id' => null, 'brand' => null, 'qty' => null]))->values();
@endphp

@section('actions')
    <a href="{{ route('admin.formulas.index') }}" class="btn">Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ $formula->exists ? route('admin.formulas.update', $formula) : route('admin.formulas.store') }}" class="space-y-6">
        @csrf
        @if ($formula->exists) @method('PUT') @endif

        <div class="card grid gap-4 p-6 sm:grid-cols-3">
            <x-admin.field name="name" label="Nama resep" :value="$formula->name" required />
            <x-admin.field name="notes" label="Catatan" :value="$formula->notes" class="sm:col-span-2" />
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Bahan per knalpot</h2>
                <p class="text-xs text-zinc-500">Kosongkan bahan atau isi qty 0 untuk menghapus baris.</p>
            </div>
            @error('lines.*')<p class="error px-5 pt-3">{{ $message }}</p>@enderror
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="w-10">#</th><th>Bahan</th><th class="w-40">Berlaku untuk merek</th><th class="w-36 num">Qty / knalpot</th><th class="w-20">Satuan</th><th class="num">Knalpot diproduksi</th><th class="num">Total terpakai</th></tr></thead>
                    <tbody>
                    @foreach ($lines as $i => $line)
                        <tr>
                            <td class="text-xs text-zinc-400">{{ $i + 1 }}</td>
                            <td>
                                <select name="lines[{{ $i }}][material_id]" class="input">
                                    <option value="">—</option>
                                    @foreach ($materials as $material)
                                        <option value="{{ $material->id }}" @selected((string) $line['material_id'] === (string) $material->id)>{{ $material->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][brand]" class="input">
                                    <option value="">Semua</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand }}" @selected($line['brand'] === $brand)>{{ $brand }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="lines[{{ $i }}][qty]" type="number" step="any" min="0" value="{{ $line['qty'] }}" class="input text-right"></td>
                            <td class="text-xs text-zinc-500">{{ $materials->firstWhere('id', $line['material_id'])?->unit }}</td>
                            @php
                                // Baris "Semua" = produk yang punya merek (wildcard Excel); baris bermerek = merek itu saja.
                                $produced = $line['material_id']
                                    ? ($line['brand'] ? (float) ($producedByBrand[$line['brand']] ?? 0) : (float) $producedByBrand->filter(fn ($q, $b) => trim((string) $b) !== '')->sum())
                                    : null;
                            @endphp
                            <td class="num">{{ $produced === null ? '' : qty($produced) }}</td>
                            <td class="num font-medium">{{ $produced === null ? '' : qty($produced * (float) $line['qty']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between gap-2">
            <div>
                @if ($formula->exists)
                    <button form="delete-formula" class="btn btn-danger">Hapus resep</button>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.formulas.index') }}" class="btn">Batal</a>
                <button class="btn btn-primary">Simpan resep</button>
            </div>
        </div>
    </form>

    @if ($formula->exists)
        <form id="delete-formula" method="POST" action="{{ route('admin.formulas.destroy', $formula) }}" onsubmit="return confirm('Hapus resep ini?')">
            @csrf
            @method('DELETE')
        </form>
    @endif
@endsection
