@extends('layouts.admin', ['title' => 'Data Ongkos'])

@section('actions')
    <a href="{{ route('admin.formulas.index') }}" class="btn">Formula</a>
@endsection

@section('content')
    <section class="card p-5 sm:p-6">
        <div class="flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-[12px] bg-linear-to-br from-[#fde39a] to-[#eba50f] text-white">
                <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ \App\Support\AdminNavigation::icon('coins') }}"/></svg>
            </span>
            <div>
                <h2 class="card-title">Tambah ongkos</h2>
                <p class="text-[12px] text-muted">Upah tenaga kerja per knalpot di luar bahan. <strong>Termasuk gaji harian</strong> = hanya dibebankan ke HPP (dibayar lewat gaji harian). <strong>Borongan</strong> = otomatis masuk slip gaji penanggung jawab produksi.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.cost-options.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_13rem_auto] sm:items-end">
            @csrf
            <x-admin.field name="name" label="Nama ongkos" required placeholder="mis. Ongkos las" />
            <x-admin.field name="amount" label="Tarif bawaan (Rp / knalpot)" type="number" step="any" min="0" />
            <x-admin.select name="payment_mode" label="Cara bayar" :options="\App\Models\CostOption::PAYMENT_MODES" value="gaji" :placeholder="false" />
            <button class="btn btn-primary h-10">+ Tambah ongkos</button>
        </form>
    </section>

    <section class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th class="w-20">Urutan</th><th>Nama ongkos</th><th>Tarif bawaan / knalpot</th><th>Cara bayar</th><th>Dipakai</th><th></th></tr></thead>
                <tbody>
                @forelse ($options as $option)
                    @php $used = (int) ($usage[mb_strtolower($option->name)] ?? 0); $formId = 'cost-'.$option->id; @endphp
                    <tr>
                        <td><input form="{{ $formId }}" name="sort_order" type="number" min="0" value="{{ $option->sort_order }}" class="input h-9 w-16 text-center text-[12px]" aria-label="Urutan"></td>
                        <td class="min-w-56"><input form="{{ $formId }}" name="name" value="{{ $option->name }}" class="input h-9 text-[13px] font-semibold" aria-label="Nama ongkos"></td>
                        <td class="w-48"><input form="{{ $formId }}" name="amount" type="number" step="any" min="0" value="{{ (float) $option->amount }}" class="input h-9 text-right text-[13px]" aria-label="Tarif bawaan"></td>
                        <td class="w-52">
                            <select form="{{ $formId }}" name="payment_mode" class="input h-9 text-[13px]" aria-label="Cara bayar">
                                @foreach (\App\Models\CostOption::PAYMENT_MODES as $mode => $label)<option value="{{ $mode }}" @selected($option->payment_mode === $mode)>{{ $label }}</option>@endforeach
                            </select>
                        </td>
                        <td><span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium {{ $used ? 'bg-brand-50 text-brand-700' : 'bg-surface text-muted' }}">{{ $used }} formula</span></td>
                        <td class="text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('admin.cost-options.update', $option) }}" id="{{ $formId }}" class="inline">@csrf @method('PUT')<button class="btn btn-sm">Simpan</button></form>
                            <x-admin.delete-button :action="route('admin.cost-options.destroy', $option)" label="✕" :confirm="'Hapus '.$option->name.' dari daftar? Formula yang sudah ada tidak berubah.'" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-10 text-center text-muted">Belum ada ongkos. Tambahkan di atas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
