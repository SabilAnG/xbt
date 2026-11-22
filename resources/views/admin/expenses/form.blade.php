@extends('layouts.admin', ['title' => $expense->exists ? 'Ubah pengeluaran' : 'Pengeluaran baru', 'subtitle' => $expense->reference_number ?? 'Gaji, host live, jasa, packing, operasional, kasbon, dll.'])

@section('content')
    <form method="POST" action="{{ $expense->exists ? route('admin.expenses.update', $expense) : route('admin.expenses.store') }}" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @if ($expense->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.field name="spent_at" label="Tanggal" type="date" :value="$expense->spent_at?->toDateString()" required />
            <x-admin.select name="expense_category_id" label="Kategori" :options="$categories->pluck('name', 'id')" :value="$expense->expense_category_id" required />
            <x-admin.field name="amount" label="Nominal" type="number" step="any" min="0" :value="$expense->amount ? (float) $expense->amount : null" required />
            <x-admin.field name="description" label="Keterangan" :value="$expense->description" required class="sm:col-span-3" />
            <x-admin.field name="paid_to" label="Ke rekening" :value="$expense->paid_to" />
            <x-admin.field name="from_account" label="Dari rekening" :value="$expense->from_account" />
            <x-admin.select name="data_status" label="Status data" :options="collect($dataStatuses)->mapWithKeys(fn ($s) => [$s => $s])" :value="$expense->data_status" placeholder="OK" hint="PERLU DIRINCI = isi belanja belum jelas." />
        </div>
        <x-admin.field name="notes" label="Catatan" type="textarea" :value="$expense->notes" />
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.expenses.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
