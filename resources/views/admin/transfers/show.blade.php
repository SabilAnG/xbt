@extends('layouts.admin', ['title' => 'Pindah barang '.$transfer->transfer_number, 'subtitle' => $transfer->transferred_at->translatedFormat('d M Y').' · '.$transfer->fromWarehouse->name.' → '.$transfer->toWarehouse->name.($transfer->moved_by ? ' · oleh '.$transfer->moved_by : '')])

@section('content')
    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Barang</th><th>Potongan</th><th class="num">Jumlah</th><th class="num">Nilai</th></tr></thead>
                <tbody>
                @foreach ($transfer->items as $line)
                    <tr>
                        <td><a href="{{ route('admin.materials.show', $line->material) }}" class="link">{{ $line->material->label() }}</a></td>
                        <td class="font-mono text-xs">{{ $line->piece?->code ?? '—' }}</td>
                        <td class="num whitespace-nowrap">{{ qty($line->qty, 2) }} {{ $line->material->stockUnit() }}</td>
                        <td class="num">{{ rupiah($line->value) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($transfer->notes)
        <section class="card p-5 text-sm whitespace-pre-line text-slate-600">{{ $transfer->notes }}</section>
    @endif

    <div class="border-t border-slate-200 pt-4">
        <x-admin.delete-button :action="route('admin.transfers.destroy', $transfer)" label="Batalkan pindah barang" confirm="Batalkan? Barang dikembalikan ke gudang asal." />
    </div>
@endsection
