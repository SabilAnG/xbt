@php
    use App\Models\Warehouse;
    use App\Support\AdminNavigation;

    // Warna & ikon gudang, sama dengan halaman Gudang.
    $styles = [
        Warehouse::FINISHED => ['from-[#fdbac4] to-[#df3f58]', '#e5465c', 'box'],
        Warehouse::RAW => ['from-[#fbbf94] to-[#e9643a]', '#ee7a45', 'warehouse'],
        Warehouse::SEMI => ['from-[#9fe3f5] to-[#2fa9d6]', '#3fbbe2', 'layers'],
        Warehouse::ACCESSORY => ['from-[#fde39a] to-[#eba50f]', '#eeaa12', 'tag'],
    ];
    $isProduct = $warehouse->type === Warehouse::FINISHED;
@endphp
@extends('layouts.admin', ['title' => 'Stok Opname'])

@section('actions')
    <a href="{{ route('admin.adjustments.index') }}" class="btn">Penyesuaian stok</a>
@endsection

@section('content')
    {{-- Pilih gudang --}}
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tiles as $tile)
            @php [$gradient, $color, $icon] = $styles[$tile['warehouse']->type]; $active = $tile['warehouse']->is($warehouse); @endphp
            <a href="{{ route('admin.stock-opname.index', ['gudang' => $tile['warehouse']->slug()]) }}"
               @class(['card flex items-center gap-3.5 p-4 transition hover:-translate-y-0.5 hover:shadow-(--shadow-pop)', 'ring-2 ring-accent' => $active])>
                <span class="grid size-11 shrink-0 place-items-center rounded-[13px] bg-linear-to-br {{ $gradient }} text-white" style="box-shadow: 0 10px 20px -10px {{ $color }}">
                    <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="{{ AdminNavigation::icon($icon) }}"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span @class(['block truncate text-[13.5px] font-semibold', 'text-accent' => $active, 'text-ink' => ! $active])>{{ $tile['warehouse']->name }}</span>
                    <span class="block truncate text-[11.5px] text-muted">{{ $tile['count'] }} {{ $tile['warehouse']->type === Warehouse::FINISHED ? 'produk' : 'bahan' }} · {{ rupiah($tile['value']) }}</span>
                </span>
                @if ($active)<span class="size-2 shrink-0 rounded-full bg-accent"></span>@endif
            </a>
        @endforeach
    </section>

    <form method="POST" action="{{ $isProduct ? route('admin.stock-opname.items') : route('admin.stock-opname.materials') }}" class="card overflow-hidden" data-opname>
        @csrf
        @unless ($isProduct)<input type="hidden" name="warehouse" value="{{ $warehouse->type }}">@endunless

        {{-- Judul + alat bantu --}}
        <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
            <div class="min-w-0 flex-1">
                <h2 class="card-title">Opname {{ $warehouse->name }}</h2>
                <p class="text-[12px] text-muted">Isi stok hasil hitung fisik. Kosong / sama dengan sistem = tidak diubah.{{ $isProduct ? '' : ' Pipa dihitung per batang di Penyesuaian Stok.' }}
                    @if ($lastCount)<span class="ml-1 text-ink-soft">Opname terakhir {{ $lastCount->adjusted_at->translatedFormat('d M Y') }} oleh <strong>{{ $lastCount->counted_by }}</strong>.</span>@endif
                </p>
            </div>
            <label class="relative w-full sm:w-60">
                <svg class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" class="input pl-9" placeholder="Cari {{ $isProduct ? 'produk' : 'bahan' }}…" data-search>
            </label>
            <button type="button" class="btn btn-sm" data-fill title="Isi semua kolom stok fisik dengan stok sistem, lalu ubah yang berbeda saja">Isi = sistem</button>
            <button type="button" class="btn btn-sm" data-clear>Kosongkan</button>
        </div>

        {{-- Data opname + ringkasan --}}
        <div class="grid items-end gap-3 border-b border-line bg-hover/40 px-5 py-4 sm:grid-cols-2 xl:grid-cols-[170px_minmax(0,1fr)_220px_minmax(0,1.2fr)]">
            <x-admin.field name="adjusted_at" label="Tanggal hitung" type="date" :value="now()->toDateString()" required />
            <div>
                <label for="counted_by" class="label">Pelaksana opname <span class="text-rose-500">*</span></label>
                <input id="counted_by" name="counted_by" value="{{ old('counted_by') }}" class="input" list="opname-counters" placeholder="Nama yang menghitung" required>
                <datalist id="opname-counters">@foreach ($counters as $name)<option value="{{ $name }}">@endforeach</datalist>
                @error('counted_by')<p class="error">{{ $message }}</p>@enderror
            </div>
            @if ($isProduct)
                <x-admin.select name="reason" label="Alasan" :options="$reasons" value="opname" :placeholder="false" required />
            @else
                <div><p class="label">Alasan</p><p class="flex h-10 items-center text-[13px] text-ink-soft">Koreksi hitung fisik</p></div>
            @endif
            <x-admin.field name="notes" label="Catatan" placeholder="mis. opname akhir bulan" />
            <div class="flex flex-wrap items-center justify-end gap-2 sm:col-span-2 xl:col-span-4">
                <span class="pill">Berubah <strong class="ml-1 text-ink" data-count>0</strong></span>
                <span class="pill">Selisih <strong class="ml-1 tabular-nums" data-total>Rp 0</strong></span>
                <button class="btn btn-primary">Simpan opname</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                <tr>
                    <th>{{ $isProduct ? 'Produk' : 'Bahan' }}</th>
                    <th class="num">Stok sistem</th>
                    <th class="num">Stok fisik</th>
                    <th class="num">Selisih</th>
                    <th class="num">Nilai selisih</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr data-row data-name="{{ mb_strtolower($row['name'].' '.$row['meta']) }}" data-system="{{ $row['system'] }}" data-cost="{{ $row['cost'] }}">
                        <td class="min-w-48">
                            @if ($row['href'])<a href="{{ $row['href'] }}" class="link font-medium">{{ $row['name'] }}</a>@else<span class="font-medium text-ink">{{ $row['name'] }}</span>@endif
                            @if ($row['meta'])<p class="text-[11px] text-muted">{{ $row['meta'] }}</p>@endif
                        </td>
                        <td class="num whitespace-nowrap">{{ qty($row['system']) }} <span class="text-[11px] text-muted">{{ $row['unit'] }}</span></td>
                        <td class="num">
                            <input name="actual[{{ $row['id'] }}]" type="number" step="any" min="0" value="{{ old('actual.'.$row['id']) }}" placeholder="{{ qty($row['system']) }}"
                                   class="input ml-auto h-9 w-28 text-right tabular-nums" data-actual>
                        </td>
                        <td class="num"><span class="text-muted" data-diff>—</span></td>
                        <td class="num tabular-nums" data-value>—</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-10 text-center text-muted">{{ $isProduct ? 'Belum ada produk aktif.' : 'Belum ada bahan di gudang ini.' }}</td></tr>
                @endforelse
                <tr data-no-match hidden><td colspan="5" class="py-8 text-center text-muted">Tidak ada yang cocok dengan pencarian.</td></tr>
                </tbody>
            </table>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-opname]');
        if (!form) return;
        const rows = [...form.querySelectorAll('[data-row]')];
        const rp = v => (v < 0 ? '−' : '') + 'Rp ' + Math.round(Math.abs(v)).toLocaleString('id-ID');
        const num = v => v.toLocaleString('id-ID', { maximumFractionDigits: 2 });

        function refresh() {
            let count = 0, total = 0;
            rows.forEach(row => {
                const input = row.querySelector('[data-actual]');
                const diffEl = row.querySelector('[data-diff]'), valueEl = row.querySelector('[data-value]');
                if (input.value === '') { diffEl.textContent = valueEl.textContent = '—'; diffEl.className = 'text-muted'; valueEl.className = 'num tabular-nums'; return; }
                const diff = parseFloat(input.value) - parseFloat(row.dataset.system);
                const same = Math.abs(diff) < 0.0001;
                const value = diff * parseFloat(row.dataset.cost);
                if (!same) { count++; total += value; }
                diffEl.textContent = same ? 'cocok' : (diff > 0 ? '+' : '−') + num(Math.abs(diff));
                diffEl.className = 'inline-flex rounded-full px-2 py-0.5 text-[11.5px] font-semibold ' + (same ? 'bg-chip text-muted' : diff < 0 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-700');
                valueEl.textContent = same ? '—' : rp(value);
                valueEl.className = 'num tabular-nums ' + (value < 0 && !same ? 'text-rose-600' : '');
            });
            form.querySelector('[data-count]').textContent = count;
            const totalEl = form.querySelector('[data-total]');
            totalEl.textContent = rp(total);
            totalEl.className = 'ml-1 tabular-nums ' + (total < 0 ? 'text-rose-600' : 'text-ink');
        }

        form.querySelector('[data-search]').addEventListener('input', e => {
            const term = e.target.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach(row => { row.hidden = term !== '' && !row.dataset.name.includes(term); if (!row.hidden) shown++; });
            form.querySelector('[data-no-match]').hidden = shown > 0 || rows.length === 0;
        });
        form.querySelector('[data-fill]').addEventListener('click', () => { rows.forEach(row => { if (row.querySelector('[data-actual]').value === '') row.querySelector('[data-actual]').value = parseFloat(row.dataset.system); }); refresh(); });
        form.querySelector('[data-clear]').addEventListener('click', () => { rows.forEach(row => row.querySelector('[data-actual]').value = ''); refresh(); });
        form.addEventListener('input', e => { if (e.target.matches('[data-actual]')) refresh(); });
        // Enter di kolom stok fisik pindah ke baris berikutnya, bukan langsung menyimpan.
        form.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || !e.target.matches('[data-actual]')) return;
            e.preventDefault();
            const inputs = rows.filter(row => !row.hidden).map(row => row.querySelector('[data-actual]'));
            inputs[inputs.indexOf(e.target) + 1]?.focus();
        });
        refresh();
    })();
</script>
@endpush
