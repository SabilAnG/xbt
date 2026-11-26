@extends('layouts.admin', ['title' => 'Mutasi manual'])

@section('content')
    <form method="POST" action="{{ route('admin.wallets.manual.store') }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.select name="wallet_id" label="Dompet" :options="$wallets->mapWithKeys(fn ($w) => [$w->id => $w->name.' — '.rupiah($w->current_balance)])" :value="request('wallet')" required />
            <x-admin.select name="direction" label="Jenis" :options="['in' => 'Uang masuk', 'out' => 'Uang keluar']" :placeholder="false" required />
            <x-admin.field name="amount" label="Nominal" type="number" step="any" min="0" required />
            <x-admin.field name="occurred_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.field name="description" label="Keterangan" required class="sm:col-span-2" placeholder="mis. Setor modal awal" />
        </div>
        <p class="text-xs text-slate-500">Untuk penjualan dan pengeluaran, pilih dompet langsung di form transaksinya supaya tidak tercatat dua kali.</p>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.wallets.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
