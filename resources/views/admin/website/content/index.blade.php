@extends('layouts.admin', ['title' => 'Konten Halaman', 'subtitle' => 'Teks dan gambar di halaman website. Kosongkan isi untuk kembali ke teks bawaan.'])

@section('content')
    <x-admin.filters>
        <div class="min-w-56 flex-1">
            <label class="label" for="q">Cari</label>
            <input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Isi teks, deskripsi, atau key">
        </div>
        <x-admin.select name="page" label="Halaman" :options="$pages" :value="request('page')" placeholder="Semua halaman" />
        <x-admin.select name="type" label="Jenis" :options="['text' => 'Teks', 'html' => 'Teks + HTML', 'image' => 'Gambar']" :value="request('type')" placeholder="Semua" />
    </x-admin.filters>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Halaman</th><th>Deskripsi</th><th>Isi</th><th>Jenis</th><th></th></tr></thead>
                <tbody>
                @forelse ($blocks as $block)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $pages[$block->page] ?? $block->page }}</td>
                        <td class="min-w-40">
                            {{ $block->label }}
                            <p class="font-mono text-[11px] text-slate-400">{{ $block->key }}</p>
                        </td>
                        <td class="max-w-md">
                            @if ($block->type === 'image')
                                @if ($block->value)
                                    <img src="{{ asset($block->value) }}" alt="" class="h-12 max-w-40 rounded border border-slate-200 bg-slate-100 object-contain">
                                @else
                                    <span class="text-xs text-slate-400">— gambar bawaan —</span>
                                @endif
                            @else
                                <span class="line-clamp-2 text-sm">{{ \Illuminate\Support\Str::limit(strip_tags((string) $block->value), 160) ?: '— teks bawaan —' }}</span>
                            @endif
                        </td>
                        <td class="text-xs whitespace-nowrap">{{ ['text' => 'Teks', 'html' => 'HTML', 'image' => 'Gambar'][$block->type] ?? $block->type }}</td>
                        <td class="text-right"><a href="{{ route('admin.content-blocks.edit', [$block, 'page' => request('page')]) }}" class="btn btn-sm">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-slate-500">Tidak ada konten yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $blocks->links() }}
@endsection
