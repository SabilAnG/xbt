@extends('layouts.admin', ['title' => 'Produksi '.$production->production_number, 'subtitle' => $production->produced_at->translatedFormat('d F Y')])

@section('actions')
    <a href="{{ route('admin.productions.index') }}" class="btn">Kembali</a>
    <x-admin.delete-button :action="route('admin.productions.destroy', $production)" confirm="Hapus produksi ini? Stok knalpot dan bahan akan dikembalikan." />
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-admin.stat label="Produk" :value="$production->item?->name ?? '—'" class="col-span-2" />
        <x-admin.stat label="Qty jadi" :value="qty($production->output_qty).' unit'" />
        <x-admin.stat label="HPP total" :value="rupiah($production->total_cost)" :hint="'Biaya bahan menurut resep: '.rupiah($production->material_cost)" />
    </section>

    <div class="card p-5 text-sm">
        <p><span class="text-slate-500">Tukang:</span> {{ $production->worker ?? '—' }}</p>
        <p class="mt-1"><span class="text-slate-500">Resep:</span> {{ $production->formula?->name }}</p>
        <p class="mt-1"><span class="text-slate-500">Catatan:</span> {{ $production->notes ?? '—' }}</p>
    </div>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Bahan terpakai</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Bahan</th><th class="num">Qty</th><th class="num">Harga rata-rata</th><th class="num">Subtotal</th></tr></thead>
                <tbody>
                @foreach ($production->materials as $line)
                    <tr>
                        <td><a href="{{ route('admin.materials.show', $line->material_id) }}" class="link">{{ $line->material?->name }}</a></td>
                        <td class="num">{{ qty($line->qty) }} {{ $line->material?->unit }}</td>
                        <td class="num">{{ rupiah($line->unit_cost) }}</td>
                        <td class="num">{{ rupiah($line->subtotal) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
