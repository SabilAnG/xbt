@extends('layouts.admin', ['title' => 'Terima barang', 'subtitle' => 'Pembelian '.$purchase->invoice_number.' · '.($purchase->supplier ?? 'tanpa supplier').' · '.$purchase->purchased_at->translatedFormat('d M Y').'. Isi jumlah yang benar-benar datang; sisanya tetap ditunggu.'])

@section('content')
    <form method="POST" action="{{ route('admin.receipts.store') }}" class="max-w-5xl space-y-6">
        @csrf
        <input type="hidden" name="material_purchase_id" value="{{ $purchase->id }}">

        <section class="card space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="received_at" label="Tanggal diterima" type="date" :value="now()->toDateString()" required />
                <x-admin.field name="received_by" label="Diterima oleh" :value="$purchase->buyer" />
                <x-admin.field name="notes" label="Catatan" placeholder="mis. no. surat jalan, kondisi barang" />
            </div>
        </section>

        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Barang</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Bahan</th><th class="num">Dipesan</th><th class="num">Sudah diterima</th><th class="num">Belum datang</th><th>Diterima sekarang</th><th>Masuk ke gudang</th></tr></thead>
                    <tbody>
                    @foreach ($lines as $i => $line)
                        @php $material = $line->material; $allowed = $material->allowedWarehouseIds(); @endphp
                        <tr>
                            <td class="min-w-48">
                                {{ $material->label() }}
                                @if ($line->bar_length_cm)<p class="text-xs text-slate-500">batang {{ qty($line->bar_length_cm, 2) }} cm</p>@endif
                                <input type="hidden" name="items[{{ $i }}][material_purchase_item_id]" value="{{ $line->id }}">
                            </td>
                            <td class="num whitespace-nowrap">{{ qty($line->qty) }} {{ $material->purchaseUnit() }}</td>
                            <td class="num text-slate-500">{{ qty($line->received_qty) }}</td>
                            <td class="num font-semibold text-amber-600">{{ qty($line->outstandingQty()) }}</td>
                            <td class="w-36">
                                <input name="items[{{ $i }}][qty]" type="number" step="{{ $material->isPipe() ? 1 : 'any' }}" min="0" max="{{ $line->outstandingQty() }}" value="{{ old("items.$i.qty", $line->outstandingQty()) }}" class="input" aria-label="Diterima sekarang {{ $material->name }}">
                            </td>
                            <td class="w-52">
                                <select name="items[{{ $i }}][warehouse_id]" class="input" aria-label="Gudang {{ $material->name }}">
                                    @foreach ($warehouses as $id => $name)
                                        @if (in_array($id, $allowed, true))
                                            <option value="{{ $id }}" @selected((string) old("items.$i.warehouse_id", $line->warehouse_id ?? $material->warehouse_id) === (string) $id)>{{ $name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Isi 0 untuk barang yang belum datang. Barang yang tidak akan datang bisa dibatalkan dari halaman pembelian (tombol "Batalkan sisa").</p>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.purchases.show', $purchase) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan penerimaan</button>
        </div>
    </form>
@endsection
