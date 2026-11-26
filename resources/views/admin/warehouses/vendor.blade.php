@extends('layouts.admin', ['title' => $warehouse->name, 'subtitle' => 'Barang yang sedang dikerjakan vendor. Nilainya = bahan yang sudah dikirim; jasa vendor ditambahkan saat hasilnya diterima.'])

@section('actions')
    <a href="{{ route('admin.warehouses.index') }}" class="btn">Semua gudang</a>
    <a href="{{ route('admin.productions.create') }}" class="btn btn-primary">+ Kirim ke vendor</a>
@endsection

@section('content')
    <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
        <x-admin.stat label="Pekerjaan terbuka" :value="$open->count()" />
        <x-admin.stat label="Unit di vendor" :value="qty($open->sum(fn ($job) => $job->outstandingQty()))" />
        <x-admin.stat label="Nilai bahan di vendor" :value="rupiah($open->sum(fn ($job) => $job->outstandingValue()))" tone="brand" />
    </section>

    @foreach (['Masih di vendor' => $open, 'Selesai diterima' => $done] as $heading => $jobs)
        <section class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">{{ $heading }}</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Nomor</th><th>Dikirim</th><th>Vendor</th><th>Proses</th><th>Hasil</th><th class="num">Dikirim</th><th class="num">Diterima</th><th class="num">Sisa di vendor</th><th class="num">Nilai bahan</th></tr></thead>
                    <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="font-mono text-xs"><a href="{{ route('admin.productions.show', $job) }}" class="link">{{ $job->production_number }}</a></td>
                            <td class="whitespace-nowrap">{{ $job->produced_at->translatedFormat('d M Y') }}</td>
                            <td>{{ $job->vendor }}</td>
                            <td class="text-sm">{{ $job->process ?? '—' }}</td>
                            <td class="text-sm">{{ $job->formula->outputMaterial?->label() }}</td>
                            <td class="num">{{ qty($job->qty) }}</td>
                            <td class="num">{{ qty($job->received_qty) }}</td>
                            <td class="num font-semibold {{ $job->outstandingQty() > 0 ? 'text-violet-700' : 'text-slate-400' }}">{{ qty($job->outstandingQty()) }}</td>
                            <td class="num">{{ rupiah($job->outstandingValue()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="py-6 text-center text-slate-500">Tidak ada.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
@endsection
