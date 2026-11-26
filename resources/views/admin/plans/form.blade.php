@extends('layouts.admin', ['title' => $plan->exists ? 'Ubah rencana '.$plan->plan_number : 'Rencana produksi baru'])

@section('content')
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="max-w-4xl space-y-6" data-rows-form>
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <section class="card space-y-4 p-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.field name="planned_for" label="Tanggal rencana" type="date" :value="optional($plan->planned_for)->toDateString()" required />
                <x-admin.field name="title" label="Judul" :value="$plan->title" class="sm:col-span-2" placeholder="mis. Produksi minggu ke-1 Desember" />
            </div>
        </section>

        <section class="card space-y-3 p-6">
            <div class="flex items-center justify-between">
                <h2 class="card-title">Formula yang dibuat</h2>
                <button type="button" class="btn btn-sm" data-add-row>+ Formula</button>
            </div>
            @error('lines')<p class="error">{{ $message }}</p>@enderror
            <div class="space-y-2" data-rows>
                @foreach ($lines as $i => $line)
                    <div class="grid grid-cols-[minmax(0,1fr)_8rem_auto] items-start gap-2" data-row>
                        <div>
                            <select name="lines[{{ $i }}][formula_id]" class="input" aria-label="Formula">
                                <option value="">— pilih formula —</option>
                                @foreach ($formulas as $id => $label)
                                    <option value="{{ $id }}" @selected((string) ($line['formula_id'] ?? '') === (string) $id)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("lines.$i.formula_id")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input name="lines[{{ $i }}][qty]" type="number" step="any" min="0" value="{{ $line['qty'] ?? '' }}" class="input" placeholder="Unit" aria-label="Jumlah">
                            @error("lines.$i.qty")<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <button type="button" class="btn btn-ghost px-2 text-rose-600" data-remove-row aria-label="Hapus baris">✕</button>
                    </div>
                @endforeach
            </div>
        </section>

        <x-admin.field name="notes" label="Catatan" type="textarea" :value="$plan->notes" />

        <div class="flex justify-end gap-2">
            <a href="{{ $plan->exists ? route('admin.plans.show', $plan) : route('admin.plans.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan & hitung kebutuhan</button>
        </div>
    </form>
@endsection

@push('scripts')
    @include('admin.partials.repeat-rows')
@endpush
