@extends('layouts.admin', ['title' => 'Pengaturan Produksi'])

@section('content')
    <form method="POST" action="{{ route('admin.production-settings.update') }}" class="card max-w-xl space-y-4 p-6">
        @csrf
        @method('PUT')
        <x-admin.field name="kerf_mm" label="Lebar mata potong gergaji / kerf (mm)" type="number" step="any" min="0" :value="$kerfMm" required
            hint="Panjang pipa yang hilang jadi serbuk setiap kali dipotong. Isi 0 bila tidak ingin diperhitungkan." />
        <div class="flex justify-end border-t border-slate-100 pt-4">
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
