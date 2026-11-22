@extends('layouts.admin', ['title' => 'Transfer antar dompet', 'subtitle' => 'mis. tarik tunai dari BCA ke kas bengkel, atau setor kas ke rekening.'])

@section('content')
    <form method="POST" action="{{ route('admin.wallets.transfer.store') }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.select name="from_wallet_id" label="Dari dompet" :options="$wallets->mapWithKeys(fn ($w) => [$w->id => $w->name.' — '.rupiah($w->current_balance)])" :value="request('from')" required />
            <x-admin.select name="to_wallet_id" label="Ke dompet" :options="$wallets->mapWithKeys(fn ($w) => [$w->id => $w->name.' — '.rupiah($w->current_balance)])" required />
            <x-admin.field name="amount" label="Nominal" type="number" step="any" min="0" required />
            <x-admin.field name="transferred_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
            <x-admin.field name="notes" label="Catatan" class="sm:col-span-2" />
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ route('admin.wallets.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan transfer</button>
        </div>
    </form>
@endsection
