@extends('layouts.admin', ['title' => 'Ubah konten', 'subtitle' => ($pages[$block->page] ?? $block->page).' · '.$block->key])

@section('actions')
    <a href="{{ route('admin.content-blocks.index', ['page' => request('page')]) }}" class="btn">Kembali</a>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.content-blocks.update', $block) }}" enctype="multipart/form-data" class="card max-w-3xl space-y-6 p-6">
        @csrf
        @method('PUT')
        <input type="hidden" name="page" value="{{ request('page') }}">

        <x-admin.field name="label" label="Deskripsi (hanya untuk admin)" :value="$block->label" required />

        @if ($block->type === 'image')
            <div>
                <p class="label">Gambar saat ini</p>
                @if ($block->value)
                    <img src="{{ asset($block->value) }}" alt="" class="max-h-56 rounded-lg border border-zinc-200 bg-zinc-100 object-contain">
                    <p class="hint font-mono">{{ $block->value }}</p>
                @else
                    <p class="text-sm text-zinc-500">Memakai gambar bawaan halaman.</p>
                @endif
            </div>
            <div>
                <label class="label" for="image">Ganti gambar</label>
                <input id="image" name="image" type="file" accept="image/*" class="input">
                @error('image')<p class="error">{{ $message }}</p>@enderror
            </div>
        @else
            <x-admin.field name="value" :label="$block->type === 'html' ? 'Teks (HTML diperbolehkan)' : 'Teks'" type="textarea" :value="$block->value" rows="6"
                :hint="$block->type === 'html' ? 'Ditampilkan tanpa escape: tanda & dan kutip tetap seperti diketik, tag HTML akan dirender.' : 'Kosongkan untuk memakai teks bawaan halaman.'" />
        @endif

        <div class="flex justify-end gap-2 border-t border-zinc-100 pt-4">
            <a href="{{ route('admin.content-blocks.index', ['page' => request('page')]) }}" class="btn">Batal</a>
            <button class="btn btn-primary">Simpan</button>
        </div>
    </form>
@endsection
