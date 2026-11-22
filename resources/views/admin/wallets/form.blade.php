@extends('layouts.admin', ['title' => $wallet->exists ? 'Ubah dompet' : 'Dompet baru', 'subtitle' => 'Rekening bank, e-wallet (DANA, OVO, GoPay, …), atau kas tunai.'])

@section('content')
    <form method="POST" action="{{ $wallet->exists ? route('admin.wallets.update', $wallet) : route('admin.wallets.store') }}" class="card max-w-2xl space-y-6 p-6">
        @csrf
        @if ($wallet->exists) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.field name="name" label="Nama dompet" :value="$wallet->name" required placeholder="mis. BCA Usaha, DANA Amin, Kas Bengkel" />
            <x-admin.select name="type" label="Jenis" :options="\App\Models\Wallet::TYPES" :value="$wallet->type" :placeholder="false" required />
            <x-admin.field name="account_number" label="No. rekening / nomor HP" :value="$wallet->account_number" />
            <x-admin.field name="holder_name" label="Atas nama" :value="$wallet->holder_name" />
            <x-admin.field name="opening_balance" label="Saldo awal" type="number" step="any" :value="(float) $wallet->opening_balance"
                hint="Saldo saat mulai dicatat di aplikasi. Mengubahnya menghitung ulang saldo berjalan." />
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $wallet->is_active))> Aktif (bisa dipilih di transaksi)
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
            <a href="{{ $wallet->exists ? route('admin.wallets.show', $wallet) : route('admin.wallets.index') }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
