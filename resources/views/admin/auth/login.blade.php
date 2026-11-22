<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap">
    @vite('resources/css/admin.css')
</head>
<body class="min-h-screen bg-white font-sans antialiased">
<div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
    {{-- Panel merek --}}
    <aside class="relative hidden overflow-hidden bg-slate-950 p-12 text-white lg:flex lg:flex-col">
        <div class="pointer-events-none absolute -top-40 -right-40 size-[520px] rounded-full bg-brand-600/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-48 -left-32 size-[460px] rounded-full bg-brand-500/10 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgb(255_255_255/0.035)_1px,transparent_1px),linear-gradient(90deg,rgb(255_255_255/0.035)_1px,transparent_1px)] bg-size-[44px_44px]"></div>

        <div class="relative flex items-center gap-3">
            <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold shadow-lg">XBT</span>
            <span class="text-sm font-semibold tracking-tight">Knalpot Admin</span>
        </div>

        <div class="relative mt-auto max-w-md">
            <h1 class="text-[34px] leading-[1.15] font-semibold tracking-tight">Produksi, stok, dan penjualan knalpot dalam satu panel.</h1>
            <p class="mt-4 text-[15px] leading-relaxed text-slate-400">Angka yang sama dengan buku kerja Excel Anda — dihitung otomatis setiap ada produksi, penjualan, dan belanja bahan.</p>
            <ul class="mt-10 space-y-3.5 text-sm text-slate-300">
                @foreach (['Stok knalpot & bahan selalu terbaru', 'Formula HPP dengan harga jual per tingkatan', 'Katalog website & pesanan via WhatsApp'] as $point)
                    <li class="flex items-center gap-3">
                        <span class="grid size-5 place-items-center rounded-full bg-brand-500/20 text-brand-500">
                            <svg class="size-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>
                        </span>{{ $point }}
                    </li>
                @endforeach
            </ul>
        </div>
        <p class="relative mt-12 text-xs text-slate-500">© {{ now()->year }} {{ config('app.name') }}</p>
    </aside>

    {{-- Form --}}
    <main class="flex items-center justify-center px-5 py-12 sm:px-10">
        <div class="w-full max-w-[380px]">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white">XBT</span>
                <span class="text-sm font-semibold text-slate-900">Knalpot Admin</span>
            </div>

            <h2 class="text-2xl font-semibold tracking-tight text-slate-900">Masuk</h2>
            <p class="mt-1.5 text-sm text-slate-500">Gunakan akun admin untuk mengelola panel.</p>

            <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-5">
                @csrf
                <x-admin.field name="email" label="Email" type="email" required autofocus autocomplete="username" placeholder="nama@domain.com" />
                <x-admin.field name="password" label="Password" type="password" required autocomplete="current-password" placeholder="••••••••" />
                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                    <input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini
                </label>
                <button class="btn btn-primary h-10 w-full text-sm">Masuk</button>
            </form>

            <a href="{{ route('home') }}" class="mt-8 inline-flex items-center gap-1.5 text-[13px] text-slate-500 hover:text-slate-800">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18 9 12l6-6"/></svg>
                Kembali ke website
            </a>
        </div>
    </main>
</div>
</body>
</html>
