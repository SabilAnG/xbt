@extends('layouts.admin', ['title' => 'Input pembelian', 'subtitle' => 'Jangan dicatat dua kali: pilih Pembelian Bahan ATAU Pengeluaran untuk belanja yang sama'])

@section('content')
    <form method="POST" action="{{ route('admin.purchases.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @php $dest = old('destination', $destination); @endphp
        <fieldset data-destination>
            <legend class="label">Masuk ke gudang <span class="text-red-500">*</span></legend>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach (['bahan_baku' => ['Gudang Bahan Baku', 'Pipa, plat, baut, gaswool, packing, …'], 'setengah_jadi' => ['Gudang Setengah Jadi', 'Komponen yang dibeli jadi: leheran, tutup silencer, moncong, …']] as $value => [$title, $hint])
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="destination" value="{{ $value }}" class="mt-1" @checked($dest === $value)>
                        <span><span class="block text-sm font-medium text-slate-900">{{ $title }}</span><span class="block text-xs text-slate-500">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </div>
            @error('destination')<p class="error">{{ $message }}</p>@enderror
        </fieldset>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="purchased_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.select name="material_id" label="Bahan" class="sm:col-span-2" :value="request('material')" data-for="bahan_baku"
                :options="$materials->mapWithKeys(fn ($m) => [$m->id => $m->name.' ('.$m->unit.')'])" />
            <x-admin.select name="component_id" label="Komponen" class="sm:col-span-2" :value="request('component')" data-for="setengah_jadi"
                :options="$components->mapWithKeys(fn ($c) => [$c->id => $c->code.' · '.$c->name.' ('.$c->unit.')'])"
                :hint="$components->isEmpty() ? 'Belum ada komponen; tambahkan dulu di Master Komponen.' : null" />
            <x-admin.field name="qty" label="Qty" type="number" step="any" min="0" required />
            <x-admin.field name="unit_price" label="Harga satuan" type="number" step="any" min="0" required />
            <x-admin.field name="supplier" label="Toko / supplier" />
            <x-admin.wallet-select name="wallet_id" label="Dibayar dari dompet" class="sm:col-span-2" hint="Tidak dipotong bila dicentang 'sudah dicatat di Pengeluaran'." />
            <x-admin.field name="paid_from" label="Dibayar dari (catatan)" class="sm:col-span-3" />
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-700">
            <input type="checkbox" name="recorded_in_expenses" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('recorded_in_expenses'))>
            <span>Uangnya sudah dicatat di Pengeluaran <span class="block text-xs text-slate-500">Centang agar tidak dihitung dobel di dashboard. Belanja baru: biarkan kosong.</span></span>
        </label>
        <x-admin.field name="notes" label="Catatan" type="textarea" />
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.purchases.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan pembelian</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var radios = document.querySelectorAll('[data-destination] input[name=destination]');
        function sync() {
            var dest = document.querySelector('[data-destination] input[name=destination]:checked');
            dest = dest ? dest.value : 'bahan_baku';
            document.querySelectorAll('[data-for]').forEach(function (select) {
                var on = select.dataset.for === dest;
                select.closest('div').classList.toggle('hidden', !on);
                select.disabled = !on;
                select.required = on;
            });
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
@endpush
