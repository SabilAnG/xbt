@extends('layouts.admin', ['title' => 'Jasa Vendor'])

@section('content')
    <section class="card p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#dccafd] to-[#8b49dc] text-white">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('truck') }}"/></svg>
            </span>
            <div>
                <h2 class="card-title">Tambah jasa vendor</h2>
                <p class="text-[12px] text-muted">Siapa vendornya, proses apa, dan tarif berapa. Dipilih saat menjasakan komponen di formula.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.vendor-services.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_10rem_8rem_auto] xl:items-end">
            @csrf
            <div>
                <label for="vendor" class="label">Vendor</label>
                <input id="vendor" name="vendor" value="{{ old('vendor') }}" class="input" list="vendor-names" placeholder="mis. Bengkel Bending Jaya" required>
                <datalist id="vendor-names">@foreach ($vendors as $vendor)<option value="{{ $vendor }}">@endforeach</datalist>
            </div>
            <div>
                <label for="process" class="label">Proses</label>
                <input id="process" name="process" value="{{ old('process') }}" class="input" list="process-names" placeholder="mis. Bending" required>
                <datalist id="process-names">@foreach ($processes->merge(['Bending', 'Chrome', 'Cat / powder coating', 'Las', 'Poles', 'Bubut']) ->unique() as $process)<option value="{{ $process }}">@endforeach</datalist>
            </div>
            <x-admin.field name="rate" label="Tarif (Rp)" type="number" step="any" min="0" required />
            <x-admin.field name="unit" label="Per" value="buah" />
            <button class="btn btn-primary h-10">+ Tambah jasa</button>
        </form>
    </section>

    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Vendor atau proses">
        </div>
    </x-admin.filters>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Vendor</th><th>Proses</th><th>Tarif</th><th>Per</th><th>Dipakai</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($services as $service)
                    @php $used = $service->components_count + $service->formulas_count; $formId = 'service-'.$service->id; @endphp
                    <tr>
                        <td class="min-w-48">
                            <input form="{{ $formId }}" name="vendor" value="{{ $service->supplier->name }}" list="vendor-names" class="input h-9 text-[13px] font-semibold" aria-label="Vendor">
                        </td>
                        <td class="min-w-40"><input form="{{ $formId }}" name="process" value="{{ $service->process }}" list="process-names" class="input h-9 text-[13px]" aria-label="Proses"></td>
                        <td class="w-36"><input form="{{ $formId }}" name="rate" type="number" step="any" min="0" value="{{ (float) $service->rate }}" class="input h-9 text-right text-[13px] font-semibold" aria-label="Tarif"></td>
                        <td class="w-28"><input form="{{ $formId }}" name="unit" value="{{ $service->unit }}" class="input h-9 text-[13px]" aria-label="Satuan"></td>
                        <td><span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $used ? 'bg-brand-50 text-brand-700' : 'bg-surface text-muted' }}">{{ $used }} komponen</span></td>
                        <td>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 text-[12px] text-ink-soft">
                                <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                                <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" @checked($service->is_active)> Aktif
                            </label>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.vendor-services.update', $service) }}" id="{{ $formId }}" class="inline">@csrf @method('PUT')<button class="btn btn-sm">Simpan</button></form>
                            @unless ($used)
                                <x-admin.delete-button :action="route('admin.vendor-services.destroy', $service)" label="✕" :confirm="'Hapus jasa '.$service->process.' dari '.$service->supplier->name.'?'" />
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-muted">Belum ada jasa vendor. Tambahkan di atas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
