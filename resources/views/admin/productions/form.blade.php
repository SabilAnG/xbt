@extends('layouts.admin', ['title' => $production->exists ? 'Ubah draf '.$production->production_number : 'Produksi baru'])

@php $vendorMode = old('mode', $production->mode) === \App\Models\Production::VENDOR; @endphp

@section('content')
    <form method="POST" action="{{ $production->exists ? route('admin.productions.update', $production) : route('admin.productions.store') }}" class="card max-w-3xl space-y-4 p-6" data-production-form>
        @csrf
        @if ($production->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="formula_id" class="label">Formula<span class="text-red-500"> *</span></label>
                <select id="formula_id" name="formula_id" class="input" required data-formula>
                    <option value="">— pilih formula —</option>
                    @foreach ($formulas->groupBy('output_type') as $type => $group)
                        <optgroup label="{{ $type === 'setengah_jadi' ? 'Barang setengah jadi' : 'Knalpot jadi' }}">
                            @foreach ($group as $formula)
                                <option value="{{ $formula->id }}" data-semi="{{ $type === 'setengah_jadi' ? 1 : 0 }}" @selected((string) old('formula_id', $production->formula_id) === (string) $formula->id)>{{ $formula->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('formula_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-admin.field name="qty" label="Jumlah" type="number" min="1" step="1" :value="(int) $production->qty" required />
            <x-admin.field name="produced_at" label="Tanggal" type="date" :value="optional($production->produced_at)->toDateString()" required />
            <div class="sm:col-span-2">
                <label for="worker" class="label">Tukang / penanggung jawab</label>
                <input id="worker" name="worker" value="{{ old('worker', $production->worker) }}" class="input" list="workers">
                <datalist id="workers">@foreach ($workers as $worker)<option value="{{ $worker }}">@endforeach</datalist>
            </div>
        </div>

        <section class="space-y-3 rounded-xl border border-violet-200 bg-violet-50/50 p-4" data-vendor-box>
            <label class="flex items-center gap-2 text-sm font-medium text-slate-800">
                <input type="hidden" name="mode" value="internal">
                <input type="checkbox" name="mode" value="vendor" data-vendor-toggle @checked($vendorMode)> Dikerjakan vendor (bending, chrome, cat, …)
            </label>
            @error('mode')<p class="error">{{ $message }}</p>@enderror
            <div @class(['grid gap-4 sm:grid-cols-3', 'hidden' => ! $vendorMode]) data-vendor-fields>
                <div>
                    <label for="vendor" class="label">Vendor</label>
                    <input id="vendor" name="vendor" value="{{ old('vendor', $production->vendor) }}" class="input" list="vendors" placeholder="Nama bengkel / vendor">
                    <datalist id="vendors">@foreach ($vendors as $vendor)<option value="{{ $vendor }}">@endforeach</datalist>
                    @error('vendor')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-admin.field name="process" label="Proses" :value="$production->process" placeholder="mis. Bending" />
                <x-admin.field name="service_cost_per_unit" label="Jasa per unit (Rp)" type="number" step="any" min="0" :value="(float) $production->service_cost_per_unit" hint="Bisa disesuaikan saat menerima." />
            </div>
            <p class="text-xs text-slate-500">Bahan keluar dari gudang saat dikirim dan tercatat di Gudang Di Vendor. Hasilnya masuk Gudang Setengah Jadi saat diterima; jasanya jadi hutang ke vendor.</p>
        </section>

        <x-admin.field name="notes" label="Catatan" type="textarea" :value="$production->notes" />

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ $production->exists ? route('admin.productions.show', $production) : route('admin.productions.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan draf</button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-production-form]');
        var formula = form.querySelector('[data-formula]');
        var box = form.querySelector('[data-vendor-box]');
        var toggle = form.querySelector('[data-vendor-toggle]');
        var fields = form.querySelector('[data-vendor-fields]');

        function sync() {
            var opt = formula.selectedOptions[0];
            var semi = opt && opt.dataset.semi === '1';
            box.classList.toggle('hidden', !semi);
            if (!semi) toggle.checked = false;
            fields.classList.toggle('hidden', !toggle.checked);
        }

        formula.addEventListener('change', sync);
        toggle.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
