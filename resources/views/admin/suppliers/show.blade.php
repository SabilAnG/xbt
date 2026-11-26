@extends('layouts.admin', ['title' => $supplier->name, 'subtitle' => collect([$supplier->phone, $supplier->address])->filter()->implode(' · ') ?: 'Supplier'])

@section('actions')
    <a href="{{ route('admin.purchases.create') }}" class="btn btn-primary">+ Pembelian</a>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-pay-many]');
        if (!form) return;
        const rp = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const cash = form.querySelector('[data-cash]'), deposit = form.querySelector('[data-use-deposit]');
        let auto = true;
        cash.addEventListener('input', () => auto = false);
        function refresh() {
            const owed = [...form.querySelectorAll('[name="purchases[]"]:checked')].reduce((sum, box) => sum + parseFloat(box.dataset.due), 0);
            const fromDeposit = deposit?.checked ? Math.min(parseFloat(deposit.dataset.balance), owed) : 0;
            form.querySelector('[data-selected]').textContent = rp(owed) + (fromDeposit ? ' − DP ' + rp(fromDeposit) : '');
            if (auto) cash.value = Math.max(Math.round(owed - fromDeposit), 0) || '';
        }
        form.addEventListener('change', e => { if (!e.target.matches('[data-cash]')) refresh(); });
        refresh();
    })();
</script>
@endpush

@section('content')
    <section class="stat-grid grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Total pembelian" :value="rupiah($total)" />
        <x-admin.stat label="Hutang" :value="rupiah($payable)" :tone="$payable > 0 ? 'danger' : 'success'" />
        <x-admin.stat label="Menunggu diterima" :value="$awaiting.' pembelian'" :tone="$awaiting > 0 ? 'warning' : 'success'" />
        <x-admin.stat label="Saldo uang muka" :value="rupiah($depositBalance)" :hint="$vendorJobs.' pekerjaan vendor'" :tone="$depositBalance > 0 ? 'success' : 'default'" />
    </section>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Pembelian</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nomor</th><th>Tanggal</th><th>Barang</th><th class="num">Total</th><th class="num">Sisa hutang</th><th>Barang</th></tr></thead>
                    <tbody>
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="font-mono text-xs whitespace-nowrap"><a href="{{ route('admin.purchases.show', $purchase) }}" class="link">{{ $purchase->invoice_number }}</a></td>
                            <td class="whitespace-nowrap">{{ $purchase->purchased_at->translatedFormat('d M Y') }}</td>
                            <td class="min-w-48 text-xs text-slate-600">{{ $purchase->isService() ? 'Jasa vendor · '.$purchase->notes : $purchase->items->map(fn ($line) => $line->material->name.' × '.qty($line->qty))->implode(' · ') }}</td>
                            <td class="num">{{ rupiah($purchase->total) }}</td>
                            <td class="num {{ $purchase->payable() > 0 ? 'text-rose-600' : 'text-slate-400' }}">{{ $purchase->payable() > 0 ? rupiah($purchase->payable()) : '—' }}</td>
                            <td>@if ($purchase->receiveStatus())<x-admin.status :value="$purchase->receiveStatus()" />@else<span class="text-xs text-slate-400">jasa</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Belum ada pembelian dari supplier ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($purchases->hasPages())<div class="border-t border-slate-100 px-5 py-3">{{ $purchases->links() }}</div>@endif
        </section>

        <div class="space-y-4 self-start">
            @if ($unpaid->isNotEmpty())
                <form method="POST" action="{{ route('admin.suppliers.pay', $supplier) }}" class="card space-y-4 p-5" data-pay-many>
                    @csrf
                    <div>
                        <h2 class="card-title">Bayar tagihan sekaligus</h2>
                        <p class="text-[12px] text-muted">Tagihan terlama dilunasi dulu{{ $depositBalance > 0 ? '; uang muka dipakai lebih dulu bila dicentang' : '' }}.</p>
                    </div>
                    <div class="max-h-64 space-y-1.5 overflow-y-auto">
                        @foreach ($unpaid as $bill)
                            <label class="flex cursor-pointer items-center gap-2.5 rounded-[10px] border border-line px-3 py-2 text-[12.5px] hover:bg-hover">
                                <input type="checkbox" name="purchases[]" value="{{ $bill->id }}" data-due="{{ $bill->payable() }}" @checked(in_array($bill->id, old('purchases', $unpaid->pluck('id')->all())))>
                                <span class="min-w-0 flex-1">
                                    <span class="font-mono text-[11.5px]">{{ $bill->invoice_number }}</span>
                                    <span class="block truncate text-[11px] text-muted">{{ $bill->purchased_at->translatedFormat('d M Y') }} · {{ $bill->isService() ? 'jasa' : 'bahan' }}</span>
                                </span>
                                <span class="font-semibold text-rose-600 tabular-nums">{{ rupiah($bill->payable()) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('purchases')<p class="error">{{ $message }}</p>@enderror
                    <p class="flex justify-between text-[13px]"><span class="text-ink-soft">Tagihan dipilih</span><strong class="tabular-nums" data-selected>—</strong></p>
                    @if ($depositBalance > 0)
                        <label class="flex items-center gap-2 text-[13px]"><input type="checkbox" name="use_deposit" value="1" checked data-use-deposit data-balance="{{ $depositBalance }}"> Pakai saldo uang muka ({{ rupiah($depositBalance) }})</label>
                    @endif
                    <x-admin.field name="paid_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                    <x-admin.field name="amount" label="Dari dompet (Rp)" type="number" step="any" min="0" data-cash />
                    <x-admin.wallet-select placeholder="— pilih dompet —" />
                    <x-admin.field name="notes" label="Catatan" placeholder="mis. transfer mingguan" />
                    <button class="btn btn-primary w-full">Bayar</button>
                </form>
            @endif

            <section class="card space-y-4 p-5">
                <div>
                    <h2 class="card-title">Uang muka (DP)</h2>
                    <p class="text-[12px] text-muted">Dibayar sebelum ada tagihan; saldo dipakai saat bayar tagihan.</p>
                </div>
                @if ($deposits->isNotEmpty())
                    <ul class="divide-y divide-line text-[12.5px]">
                        @foreach ($deposits as $deposit)
                            <li class="flex items-center gap-2 py-2">
                                <span class="min-w-0 flex-1">
                                    {{ $deposit->paid_at->translatedFormat('d M Y') }} · {{ $deposit->wallet?->name ?? '—' }}
                                    <span class="block text-[11px] text-muted">terpakai {{ rupiah($deposit->used ?? 0) }}{{ $deposit->notes ? ' · '.$deposit->notes : '' }}</span>
                                </span>
                                <strong class="tabular-nums">{{ rupiah($deposit->amount) }}</strong>
                                @if (! (float) $deposit->used)
                                    <x-admin.delete-button :action="route('admin.suppliers.deposits.destroy', $deposit)" confirm="Hapus uang muka ini? Saldo dompet dikembalikan." />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.suppliers.deposits.store', $supplier) }}" class="space-y-3 border-t border-line pt-4">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-admin.field name="paid_at" label="Tanggal" type="date" :value="now()->toDateString()" required />
                        <x-admin.field name="amount" label="Nominal (Rp)" type="number" step="any" min="0" required />
                    </div>
                    <x-admin.wallet-select placeholder="— pilih dompet —" />
                    <x-admin.field name="notes" label="Keterangan" placeholder="mis. DP bending 50 pcs" />
                    <button class="btn w-full">+ Catat uang muka</button>
                </form>
            </section>

            <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}" class="card space-y-4 p-6">
                @csrf
                @method('PUT')
                <h2 class="card-title">Data supplier</h2>
                <x-admin.field name="name" label="Nama" :value="$supplier->name" required />
                <x-admin.field name="phone" label="Telepon / WA" :value="$supplier->phone" />
                <x-admin.field name="address" label="Alamat" :value="$supplier->address" />
                <x-admin.field name="notes" label="Catatan" type="textarea" :value="$supplier->notes" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked($supplier->is_active)> Aktif (muncul di pilihan pembelian)</label>
                <div class="flex justify-end"><button class="btn btn-primary">Simpan</button></div>
            </form>
            @if ($purchases->total() === 0 && $deposits->isEmpty())
                <x-admin.delete-button :action="route('admin.suppliers.destroy', $supplier)" label="Hapus supplier" :confirm="'Hapus supplier '.$supplier->name.'?'" />
            @endif
        </div>
    </div>
@endsection
