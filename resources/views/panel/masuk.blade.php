<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/panel.css'])
</head>
<body class="grid min-h-full place-items-center px-4 py-10 text-slate-800 antialiased">

    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center gap-3">
            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-aksen-500 text-base font-bold text-white">HS</span>
            <div>
                <p class="text-base font-semibold text-slate-900">{{ config('app.name') }}</p>
                <p class="text-[0.8125rem] text-slate-500">Panel produksi</p>
            </div>
        </div>

        <form method="POST" action="{{ route('panel.masuk.kirim') }}" class="kartu space-y-4 p-6">
            @csrf

            @error('email')
                <p class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-sm text-rose-700">{{ $message }}</p>
            @enderror

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" required autofocus
                       value="{{ old('email') }}" class="isian" placeholder="nama@contoh.com">
            </div>

            <div>
                <label for="password" class="label">Kata sandi</label>
                <input id="password" name="password" type="password" required class="isian" placeholder="••••••••">
            </div>

            <label class="flex items-center gap-2 text-[0.8125rem] text-slate-600">
                <input type="checkbox" name="ingat" class="rounded border-slate-300 text-aksen-500 focus:ring-aksen-200">
                Ingat saya di peramban ini
            </label>

            <button type="submit" class="tombol tombol-utama w-full justify-center">Masuk</button>
        </form>
    </div>
</body>
</html>
