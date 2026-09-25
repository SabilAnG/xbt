@props(['judul' => null, 'keterangan' => null])

<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Panel' }} — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/panel.css', 'resources/js/panel.js'])
</head>
<body class="h-full text-slate-800 antialiased">

    @include('panel.partials.sidebar')

    {{-- Isi halaman. Digeser selebar sidebar hanya di layar lebar; di bawah itu
         sidebar jadi laci yang mengambang di atas isi. --}}
    <div class="lg:pl-[17.5rem]">

        {{-- Topbar --}}
        <header class="sticky top-0 z-20 bg-lantai/85 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <button type="button" data-buka-sidebar
                        class="tombol-hening grid h-10 w-10 place-items-center rounded-xl lg:hidden">
                    <x-panel.ikon nama="menu" kelas="h-5 w-5" />
                </button>

                <div class="min-w-0 flex-1">
                    <h1 class="truncate text-lg font-bold tracking-tight text-slate-900 sm:text-xl">
                        {{ $judul ?? '' }}
                    </h1>
                    @isset($keterangan)
                        <p class="truncate text-[0.8125rem] text-slate-500">{{ $keterangan }}</p>
                    @endisset
                </div>

                @isset($aksi)
                    <div class="flex shrink-0 items-center gap-2">{{ $aksi }}</div>
                @endisset

                {{-- Gugus ikon di kanan, seperti contoh: lonceng berlencana dan
                     avatar. Keduanya belum punya isi — lonceng menunggu modul
                     notifikasi, jadi sengaja tidak diberi tautan palsu. --}}
                <div class="hidden shrink-0 items-center gap-2 sm:flex">
                    <span class="relative grid h-10 w-10 place-items-center rounded-full bg-white text-slate-400 shadow-[var(--shadow-timbul-kecil)]">
                        <x-panel.ikon nama="cari" kelas="h-[1.15rem] w-[1.15rem]" />
                    </span>

                    <span class="grid h-10 w-10 place-items-center rounded-full bg-mint-500 text-xs font-bold text-white shadow-[0_8px_18px_-8px_var(--color-mint-500)]">
                        {{ str(auth()->user()?->name ?? '?')->substr(0, 1)->upper() }}
                    </span>
                </div>
            </div>
        </header>

        <main class="px-4 pb-8 sm:px-6">
            @if (session('sukses'))
                <div class="kartu mb-4 border-l-4 border-mint-500 px-4 py-3 text-sm text-slate-700">
                    {{ session('sukses') }}
                </div>
            @endif

            @if (session('gagal'))
                <div class="kartu mb-4 border-l-4 border-merah-500 px-4 py-3 text-sm text-slate-700">
                    {{ session('gagal') }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
