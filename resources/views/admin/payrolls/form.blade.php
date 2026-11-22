@php
    $isHost = $kind === 'host';
    $selected = collect(old('people', $row->exists ? [$isHost ? $row->host_id : $row->worker_id] : $people->pluck('id')->all()))->map(fn ($id) => (int) $id);
@endphp
@extends('layouts.admin', ['title' => ($row->exists ? 'Ubah rekap ' : 'Rekap ').strtolower($title), 'subtitle' => $isHost ? 'Total jam & nominal dihitung otomatis dari Jam Live' : 'Hari masuk & nominal dihitung otomatis dari Absen Tukang'])

@section('content')
    <form method="POST" action="{{ $row->exists ? route('admin.payrolls.update', [$kind, $row->id]) : route('admin.payrolls.store', $kind) }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="period_start" label="Periode mulai" type="date" :value="$row->period_start?->toDateString()" required />
            <x-admin.field name="period_end" label="Periode selesai" type="date" :value="$row->period_end?->toDateString()" required :hint="$isHost ? null : 'Biasanya Senin–Sabtu.'" />
        </div>

        <div>
            <p class="label">{{ $isHost ? 'Host' : 'Tukang' }}{{ $row->exists ? '' : ' (bisa pilih beberapa sekaligus)' }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($people as $person)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="{{ $row->exists ? 'radio' : 'checkbox' }}" name="people[]" value="{{ $person->id }}" class="border-slate-300" @checked($selected->contains($person->id))>
                        {{ $person->name }}
                        <span class="text-xs text-slate-400">{{ rupiah($isHost ? $person->hourly_rate : $person->daily_wage) }}/{{ $isHost ? 'jam' : 'hari' }}</span>
                    </label>
                @endforeach
            </div>
            @error('people')<p class="error">{{ $message }}</p>@enderror
        </div>

        @unless ($isHost)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.field name="bonus" label="Tambahan / lembur" type="number" step="any" min="0" :value="(float) $row->bonus ?: null" />
                <x-admin.field name="deduction" label="Potongan / kasbon" type="number" step="any" min="0" :value="(float) $row->deduction ?: null" />
            </div>
        @endunless

        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.select name="status" label="Status" :options="['Belum' => 'Belum', 'Sudah' => 'Sudah']" :value="$row->status" :placeholder="false" required />
            <x-admin.field name="paid_at" :label="$isHost ? 'Tanggal transfer' : 'Tanggal bayar'" type="date" :value="$row->paid_at?->toDateString()" />
            <x-admin.field name="notes" label="Catatan" :value="$row->notes" />
        </div>

        @if (! $row->exists || $row->status !== 'Sudah')
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input type="checkbox" name="record_expense" value="1" class="mt-0.5 rounded border-slate-300" @checked(old('record_expense'))>
                <span>Bila status "Sudah", catat juga ke Pengeluaran (kategori {{ $isHost ? 'Host Live' : 'Gaji Tukang' }})
                    <span class="block text-xs text-slate-500">Di Excel ini dicatat manual. Jangan dicentang bila sudah dicatat sendiri, supaya tidak dobel.</span></span>
            </label>
            <x-admin.wallet-select name="wallet_id" label="Dibayar dari dompet (bila dicatat ke Pengeluaran)" class="max-w-md" />
        @endif

        @if ($row->exists)
            <div class="rounded-lg bg-slate-50 p-4 text-sm">
                @if ($isHost)
                    Total jam <strong>{{ qty($row->totalHours(), 2) }}</strong> × {{ rupiah($row->rate()) }} = <strong>{{ rupiah($row->amountDue()) }}</strong>
                @else
                    {{ qty($row->daysPresent(), 1) }} hari × {{ rupiah($row->dailyWage()) }} + tambahan − potongan = <strong>{{ rupiah($row->amountDue()) }}</strong>
                @endif
            </div>
        @endif

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.payrolls.index', $kind) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
